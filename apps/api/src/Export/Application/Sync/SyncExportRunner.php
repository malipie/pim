<?php

declare(strict_types=1);

namespace App\Export\Application\Sync;

use App\Catalog\Application\Filter\FilterDslResolver;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Export\Application\Builder\ExportBuilder;
use App\Export\Application\Builder\PublicationColumnPlanner;
use App\Export\Application\Builder\Structural\StructuralExportBuilderInterface;
use App\Export\Domain\Entity\ExportSession;
use App\Export\Domain\Enum\ExportEncoding;
use App\Export\Domain\Enum\ExportEntityType;
use App\Export\Domain\Enum\ExportFormat;
use App\Export\Domain\Enum\ExportTargetScope;
use App\Export\Domain\Repository\ExportSessionRepositoryInterface;
use App\Export\Infrastructure\Writer\CsvStreamWriter;
use App\Export\Infrastructure\Writer\RowWriter;
use App\Export\Infrastructure\Writer\XlsxStreamWriter;
use App\Shared\Domain\Tenant;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
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
    public const int PROGRESS_CHUNK = 500;

    /** IMP2-2.6 — detach hydrated objects every N rows so streaming stays flat. */
    private const int CLEAR_INTERVAL = 200;

    public function __construct(
        private readonly ExportBuilder $builder,
        private readonly CatalogObjectRepositoryInterface $objects,
        private readonly ExportSessionRepositoryInterface $sessions,
        private readonly FilterDslResolver $filterDsl,
        private readonly Connection $connection,
        private readonly PublicationColumnPlanner $columnPlanner,
        private readonly ObjectTypeRepositoryInterface $objectTypes,
        private readonly EntityManagerInterface $em,
        /** @var iterable<StructuralExportBuilderInterface> */
        #[AutowireIterator('app.export.structural_builder')]
        private readonly iterable $structuralBuilders = [],
    ) {
    }

    /**
     * Count the rows an export will produce, used by the controller to route
     * sync vs async. Structural types count via their builder; catalog-object
     * types count the resolved target set.
     */
    public function resolveTargetCount(ExportSession $session): int
    {
        if ($session->getEntityType()->isStructural()) {
            return $this->structuralBuilderFor($session->getEntityType())->count($this->requireTenant($session));
        }

        // IMP2-2.6 — the streamable scope (All, masters only) counts via
        // COUNT(*) instead of hydrating the whole result set just to size it.
        if ($this->canStream($session)) {
            $tenant = $this->requireTenant($session);

            return $this->objects->countRootObjectsByType($this->resolveObjectType($session, $tenant), $tenant);
        }

        return \count($this->resolveTargets($session));
    }

    /**
     * IMP2-2.6 — a catalog-object export is streamable (root-by-root with
     * EntityManager::clear() per chunk) when its scope is All and variant
     * fan-out is off. Selected/Filter are already bounded id sets; variant
     * fan-out interleaves children and needs the materialised pass.
     */
    private function canStream(ExportSession $session): bool
    {
        return !$session->getEntityType()->isStructural()
            && ExportTargetScope::All === $session->getTargetScope()
            && !$session->includesVariants();
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

        // EXR-05: the target set is scoped to the session's ObjectType
        // (product → built-in Product type, custom_module → its own type)
        // rather than the hardcoded Product kind.
        $objectType = $this->resolveObjectType($session, $tenant);

        $base = match ($session->getTargetScope()) {
            ExportTargetScope::Selected => $this->resolveSelected($session),
            ExportTargetScope::All => $this->objects->findByObjectType($objectType, $tenant),
            ExportTargetScope::Filter => $this->resolveFilter($session, $tenant, $objectType),
        };

        return $this->applyVariantFanout($base, $session, $tenant);
    }

    /**
     * IMP2-1.8 (#1471) — `include_variants` fan-out. With the flag OFF the
     * export carries masters only; with it ON each master is immediately
     * followed by its variants (tenant-scoped child query), giving the export
     * the deterministic `master, then its variants` order the variants golden
     * relies on. A directly-selected variant whose master is absent is still
     * emitted.
     *
     * @param list<CatalogObject> $base
     *
     * @return list<CatalogObject>
     */
    private function applyVariantFanout(array $base, ExportSession $session, Tenant $tenant): array
    {
        if (!$session->includesVariants()) {
            return array_values(array_filter($base, static fn (CatalogObject $o): bool => null === $o->getParent()));
        }

        $masterIds = [];
        foreach ($base as $object) {
            if (null === $object->getParent()) {
                $masterIds[] = $object->getId()->toRfc4122();
            }
        }

        $childrenByParent = [];
        foreach ($this->objects->findChildrenByParentIds($masterIds, $tenant) as $child) {
            $parentId = $child->getParent()?->getId()->toRfc4122();
            if (null !== $parentId) {
                $childrenByParent[$parentId][] = $child;
            }
        }

        $result = [];
        $seen = [];
        foreach ($base as $object) {
            $id = $object->getId()->toRfc4122();
            if (isset($seen[$id])) {
                continue;
            }
            $result[] = $object;
            $seen[$id] = true;

            foreach ($childrenByParent[$id] ?? [] as $child) {
                $childId = $child->getId()->toRfc4122();
                if (!isset($seen[$childId])) {
                    $result[] = $child;
                    $seen[$childId] = true;
                }
            }
        }

        return $result;
    }

    /**
     * Resolve the ObjectType backing a catalog-object export session.
     *
     * Product sessions resolve the tenant's built-in Product type; custom
     * modules resolve their stored `object_type_id`. Structural entity types
     * are not catalog-object backed and must never reach this path (they are
     * rejected upstream by {@see ExportEntityType::isExecutable()}).
     */
    private function resolveObjectType(ExportSession $session, Tenant $tenant): ObjectType
    {
        return match ($session->getEntityType()) {
            ExportEntityType::Product => $this->objectTypes->findBuiltInByKind(ObjectKind::Product, $tenant)
                ?? throw new LogicException('Built-in Product ObjectType is not seeded for this tenant.'),
            ExportEntityType::CustomModule => $this->resolveCustomObjectType($session),
            default => throw new LogicException(sprintf(
                'Export entity_type "%s" is not backed by catalog objects.',
                $session->getEntityType()->value,
            )),
        };
    }

    private function resolveCustomObjectType(ExportSession $session): ObjectType
    {
        $id = $session->getObjectTypeId();
        if (null === $id) {
            throw new LogicException('custom_module export session is missing object_type_id.');
        }
        $objectType = $this->objectTypes->findById($id);
        if (null === $objectType) {
            throw new LogicException(sprintf('ObjectType "%s" was not found.', $id->toRfc4122()));
        }

        return $objectType;
    }

    /**
     * Run the export to a temporary file path and return that path.
     *
     * Caller (the controller) is responsible for streaming the file to
     * the HTTP response and deleting it afterwards. We split execution
     * from delivery so the same runner can later land async output.
     *
     * @param callable(int): void|null $onChunk EXR-15 — invoked every
     *                                          PROGRESS_CHUNK rows with rows-done; throwing
     *                                          {@see \App\Export\Application\Async\ExportCancelledException}
     *                                          aborts the run gracefully
     */
    public function runToFile(ExportSession $session, string $targetPath, ?callable $onChunk = null): int
    {
        if ($session->getEntityType()->isStructural()) {
            return $this->runStructuralToFile($session, $targetPath, $onChunk);
        }

        // IMP2-2.6 — stream the All scope so a 50k export never materialises
        // the full object graph (constant memory, enforced by the benchmark).
        if ($this->canStream($session)) {
            return $this->runStreamingToFile($session, $targetPath, $onChunk);
        }

        $targets = $this->resolveTargets($session);
        $session->setTargetCount(\count($targets));

        $columns = $session->getSelectedColumns();
        if ([] === $columns) {
            // #1235 — when no manual columns, try to derive from publication profile.
            $objectTypeIds = $this->collectObjectTypeIds($targets);
            $planned = $objectTypeIds !== []
                ? $this->columnPlanner->plan($session, $objectTypeIds)
                : null;
            if (null === $planned) {
                throw new InvalidArgumentException('Export session must list at least one column.');
            }
            $columns = $planned;
        }

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
                if (null !== $onChunk && 0 === $rows % self::PROGRESS_CHUNK) {
                    $onChunk($rows);
                }
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
     * IMP2-2.6 — streaming export of scope All (masters only). Walks the root
     * objects in keyset pages and clears the EntityManager between pages, so the
     * builder's per-object value/relation hydration never accumulates. Each page
     * gets a FRESH {@see ExportBuilder::build()} call: its attribute/channel map
     * is resolved from a clean EM, so the inter-page clear cannot leave it
     * holding detached entities. The clear also detaches the session, so it is
     * re-loaded each page (build() reads its tenant/channels) and before the
     * final markDone.
     *
     * @param (callable(int): void)|null $onChunk invoked every PROGRESS_CHUNK rows
     */
    private function runStreamingToFile(ExportSession $session, string $targetPath, ?callable $onChunk): int
    {
        $sessionId = $session->getId();
        $tenant = $this->requireTenant($session);
        $objectType = $this->resolveObjectType($session, $tenant);

        $total = $this->objects->countRootObjectsByType($objectType, $tenant);
        $session->setTargetCount($total);
        $this->sessions->save($session);

        $columns = $session->getSelectedColumns();
        if ([] === $columns) {
            // #1235 — derive columns from the publication profile of the single
            // scope-All ObjectType (no need to scan the whole target set).
            $planned = $this->columnPlanner->plan($session, [$objectType->getId()->toRfc4122()]);
            if (null === $planned) {
                throw new InvalidArgumentException('Export session must list at least one column.');
            }
            $columns = $planned;
        }

        $writer = $this->openWriter($session->getFormat(), $session, $targetPath);
        $writer->writeHeaders($columns);

        $rows = 0;
        $afterId = null;
        try {
            while (true) {
                // $objectType/$tenant may be detached after a prior page's clear;
                // Doctrine resolves them to ids for the WHERE params, so the query
                // stays correct without re-hydrating them.
                $page = $this->objects->findRootObjectsAfter($objectType, $tenant, $afterId, self::CLEAR_INTERVAL);
                if ([] === $page) {
                    break;
                }
                foreach ($this->builder->build($page, $session) as $row) {
                    $values = [];
                    foreach ($columns as $key) {
                        $values[] = $row[$key] ?? '';
                    }
                    $writer->writeRow($values);
                    ++$rows;
                    if (null !== $onChunk && 0 === $rows % self::PROGRESS_CHUNK) {
                        $onChunk($rows);
                    }
                }
                $afterId = $page[array_key_last($page)]->getId();
                // Detach the page (objects + their hydrated values) before the next.
                $this->em->clear();
                // build() needs a managed session next round (tenant/channels).
                $session = $this->sessions->findById($sessionId) ?? $session;
                $tenant = $this->requireTenant($session);
            }
        } finally {
            $writer->close();
        }

        $size = file_exists($targetPath) ? (int) filesize($targetPath) : 0;
        $session = $this->sessions->findById($sessionId) ?? $session;
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
    private function resolveFilter(ExportSession $session, Tenant $tenant, ObjectType $objectType): array
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
        // EXR-05: scope by object_type_id (any ObjectType) instead of the
        // hardcoded Product kind. The DSL itself stays ObjectType-agnostic.
        // EXR-07: alias the table as `co` — FilterDslResolver emits
        // `co.`-prefixed column references, so the FROM clause must define it.
        $sql = 'SELECT co.id FROM objects co '
            .'WHERE co.tenant_id = :tenant AND co.object_type_id = :otid AND ('.$whereClause.')';

        try {
            $rows = $this->connection->fetchAllAssociative(
                $sql,
                ['tenant' => $tenantId, 'otid' => $objectType->getId()->toRfc4122()],
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

    /**
     * Collects unique ObjectType IDs (as UUID RFC strings) from a set of objects.
     *
     * @param list<CatalogObject> $objects
     *
     * @return list<string>
     */
    private function collectObjectTypeIds(array $objects): array
    {
        $ids = [];
        foreach ($objects as $object) {
            $id = $object->getObjectType()->getId()->toRfc4122();
            $ids[$id] = $id;
        }

        return array_values($ids);
    }

    /**
     * EXR-06: structural exports (module_schema / attributes_groups /
     * categories) stream a flat config table from the matching builder rather
     * than the catalog-object pipeline.
     */
    /**
     * @param callable(int): void|null $onChunk
     */
    private function runStructuralToFile(ExportSession $session, string $targetPath, ?callable $onChunk = null): int
    {
        $tenant = $this->requireTenant($session);
        $builder = $this->structuralBuilderFor($session->getEntityType());

        $columns = $session->getSelectedColumns();
        if ([] === $columns) {
            $columns = $builder->columns($tenant);
        }

        $writer = $this->openWriter($session->getFormat(), $session, $targetPath);
        $writer->writeHeaders($columns);

        $rows = 0;
        try {
            foreach ($builder->rows($tenant) as $row) {
                $values = [];
                foreach ($columns as $key) {
                    $values[] = $row[$key] ?? '';
                }
                $writer->writeRow($values);
                ++$rows;
                if (null !== $onChunk && 0 === $rows % self::PROGRESS_CHUNK) {
                    $onChunk($rows);
                }
            }
        } finally {
            $writer->close();
        }

        $session->setTargetCount($rows);
        $size = file_exists($targetPath) ? (int) filesize($targetPath) : 0;
        $session->markDone($rows, $targetPath, $size);
        $this->sessions->save($session);

        return $rows;
    }

    private function structuralBuilderFor(ExportEntityType $type): StructuralExportBuilderInterface
    {
        foreach ($this->structuralBuilders as $builder) {
            if ($builder->supports($type)) {
                return $builder;
            }
        }

        throw new LogicException(sprintf('No structural export builder supports entity_type "%s".', $type->value));
    }

    private function requireTenant(ExportSession $session): Tenant
    {
        $tenant = $session->getTenant();
        if (null === $tenant) {
            throw new LogicException('Export session must carry a tenant.');
        }

        return $tenant;
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
