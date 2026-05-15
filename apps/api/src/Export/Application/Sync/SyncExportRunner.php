<?php

declare(strict_types=1);

namespace App\Export\Application\Sync;

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
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Symfony\Component\Uid\Uuid;

/**
 * Runs the sync (<100 row) export path end-to-end.
 *
 * Resolves the target object list per {@see ExportTargetScope}, walks
 * them through {@see ExportBuilder}, and streams the resulting rows
 * into the chosen writer. Wraps the file write in a try/finally so the
 * temp file is cleaned up even on partial failure.
 *
 * Filter snapshot resolution is intentionally deferred — the catalog
 * filter DSL evaluator (`FilterDslResolver`) lives in the Catalog
 * context and ships with the modal integration (EXP-11). The
 * `target_scope=filter` path returns a NotImplemented exception in MVP
 * sync; modal callers downgrade to selected/all until that wiring lands.
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
            ExportTargetScope::Filter => throw new RuntimeException(
                'target_scope=filter is not yet supported in MVP sync runner (Faza 1 candidate).'
            ),
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
