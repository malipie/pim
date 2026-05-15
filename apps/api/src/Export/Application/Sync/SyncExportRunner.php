<?php

declare(strict_types=1);

namespace App\Export\Application\Sync;

use App\Catalog\Application\Filter\FilterDslResolver;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Export\Application\Builder\ExportBuilder;
use App\Export\Domain\Entity\ExportSession;
use App\Export\Domain\Enum\ExportEncoding;
use App\Export\Domain\Enum\ExportFormat;
use App\Export\Domain\Enum\ExportTargetScope;
use App\Export\Domain\Repository\ExportSessionRepositoryInterface;
use App\Export\Infrastructure\Writer\CsvStreamWriter;
use App\Export\Infrastructure\Writer\RowWriter;
use App\Export\Infrastructure\Writer\XlsxStreamWriter;
use App\Shared\Domain\Tenant;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * Runs the sync (<100 row) export path end-to-end.
 *
 * Resolves the target object list per {@see ExportTargetScope}, walks
 * them through {@see ExportBuilder}, and streams the resulting rows
 * into the chosen writer. Wraps the file write in a try/finally so the
 * temp file is cleaned up even on partial failure.
 *
 * Filter scope (EXP-20 #632) compiles the session's `filter_snapshot`
 * via {@see FilterDslResolver::toCountSql()} into a tenant-scoped
 * SQL WHERE clause, fetches the matching `catalog_objects` IDs, and
 * loads the full entities through the repository — mirrors the
 * SmartFilterPresetController::resolveCounts pattern so we get the
 * same operator coverage (PRD §5.5) without re-implementing the DSL.
 *
 * Sync writes ALWAYS complete in a single request — no Mercure, no
 * status transitions beyond `done` or `error`. The async handler
 * (EXP-06) takes over from 100 rows up.
 */
final class SyncExportRunner
{
    public function __construct(
        private readonly ExportBuilder $builder,
        private readonly CatalogObjectRepositoryInterface $objects,
        private readonly ExportSessionRepositoryInterface $sessions,
        private readonly FilterDslResolver $filterDsl,
        private readonly Connection $connection,
    ) {
    }

    /**
     * Resolve target objects to export based on session scope.
     *
     * @return list<CatalogObject>
     */
    public function resolveTargets(ExportSession $session): array
    {
        $tenant = $session->getTenant();
        if (null === $tenant) {
            throw new LogicException('Export session must carry a tenant before resolveTargets().');
        }

        return match ($session->getTargetScope()) {
            ExportTargetScope::Selected => $this->resolveSelected($session),
            ExportTargetScope::All => $this->objects->findByKind(ObjectKind::Product, $tenant),
            ExportTargetScope::Filter => $this->resolveFilter($session, $tenant),
        };
    }

    /**
     * Run the export to a temporary file path and return that path.
     *
     * Caller (the controller) is responsible for streaming the file to
     * the HTTP response and deleting it afterwards. We split execution
     * from delivery so the same runner can later land async output.
     */
    public function runToFile(ExportSession $session, string $targetPath): int
    {
        $columns = $session->getSelectedColumns();
        if ([] === $columns) {
            throw new InvalidArgumentException('Export session must list at least one column.');
        }

        $targets = $this->resolveTargets($session);
        $session->setTargetCount(\count($targets));

        $writer = $this->openWriter($session->getFormat(), $session, $targetPath);
        $writer->writeHeaders($columns);

        $rows = 0;
        try {
            foreach ($this->builder->build($targets, $session) as $row) {
                $values = [];
                foreach ($columns as $key) {
                    $values[] = $row[$key] ?? '';
                }
                $writer->writeRow($values);
                ++$rows;
            }
        } finally {
            $writer->close();
        }

        $size = file_exists($targetPath) ? (int) filesize($targetPath) : 0;
        $session->markDone($rows, $targetPath, $size);
        $this->sessions->save($session);

        return $rows;
    }

    /**
     * Compile the session's filter DSL into tenant-scoped SQL, fetch the
     * matching catalog_objects IDs, and load the full entities through
     * the repository.
     *
     * @return list<CatalogObject>
     */
    private function resolveFilter(ExportSession $session, Tenant $tenant): array
    {
        $dsl = $session->getFilterSnapshot();
        if (null === $dsl || [] === $dsl) {
            return [];
        }

        $whereClause = $this->filterDsl->toCountSql($dsl);
        if (null === $whereClause) {
            throw new RuntimeException('Invalid filter DSL in export session snapshot.');
        }

        $tenantId = $tenant->getId()->toRfc4122();
        $sql = 'SELECT id FROM catalog_objects '
            .'WHERE tenant_id = :tenant AND kind = :kind AND deleted_at IS NULL AND ('.$whereClause.')';

        try {
            $rows = $this->connection->fetchAllAssociative(
                $sql,
                ['tenant' => $tenantId, 'kind' => ObjectKind::Product->value],
            );
        } catch (Throwable $error) {
            throw new RuntimeException('Filter scope SQL execution failed: '.$error->getMessage(), previous: $error);
        }

        $ids = [];
        foreach ($rows as $row) {
            if (isset($row['id']) && \is_string($row['id'])) {
                $ids[] = $row['id'];
            }
        }
        if ([] === $ids) {
            return [];
        }

        return $this->objects->findByIds($ids);
    }

    /**
     * @return list<CatalogObject>
     */
    private function resolveSelected(ExportSession $session): array
    {
        $ids = $session->getSelectedObjectIds();
        if (null === $ids || [] === $ids) {
            return [];
        }

        $uuids = array_map(static fn (string $id): Uuid => Uuid::fromString($id), $ids);

        return $this->objects->findByIds(array_map(static fn (Uuid $u): string => $u->toRfc4122(), $uuids));
    }

    private function openWriter(ExportFormat $format, ExportSession $session, string $path): RowWriter
    {
        if (ExportFormat::Xlsx === $format) {
            $xlsx = new XlsxStreamWriter();
            $xlsx->openToFile($path);

            return $xlsx;
        }

        $encoding = $session->getEncoding() ?? ExportEncoding::Utf8Bom;
        $csv = new CsvStreamWriter();
        $csv->open($path, $encoding);

        return $csv;
    }
}
