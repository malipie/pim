<?php

declare(strict_types=1);

namespace App\Catalog\Application\Handler;

use App\Catalog\Application\Message\CheckSchemaDriftForCategory;
use App\Catalog\Domain\Entity\AttributeGroup;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Service\EffectiveAttributeGroupResolver;
use App\Shared\Application\AbstractBatchHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * CHC-04 (#1288) — asynchronous schema-drift detection after a category move.
 *
 * Triggered by {@see CheckSchemaDriftForCategory} (dispatched on a confirmed
 * move that affects products). For every product assigned to the moved
 * category or any descendant, it recomputes the effective attribute-group set
 * and compares it with the captured snapshot; a difference flags
 * `schema_drift`. Off the request thread, batched to stay worker-memory-safe.
 */
#[AsMessageHandler]
final class CheckSchemaDriftHandler extends AbstractBatchHandler
{
    public function __construct(
        EntityManagerInterface $entityManager,
        private readonly EffectiveAttributeGroupResolver $resolver,
    ) {
        parent::__construct($entityManager);
    }

    public function __invoke(CheckSchemaDriftForCategory $message): void
    {
        $category = $this->entityManager->find(CatalogObject::class, Uuid::fromString($message->categoryId));
        if (!$category instanceof CatalogObject) {
            return;
        }
        $path = $category->getPath();
        $tenant = $category->getTenant();
        if (null === $path || '' === $path || null === $tenant) {
            return;
        }

        $processed = 0;
        foreach ($this->affectedProductIds($tenant->getId()->toRfc4122(), $path) as $productId) {
            $product = $this->entityManager->find(CatalogObject::class, Uuid::fromString($productId));
            if (!$product instanceof CatalogObject) {
                continue;
            }
            if ($this->hasDrifted($product)) {
                $product->flagSchemaDrift(true);
            }

            ++$processed;
            if ($this->shouldFlush($processed)) {
                $this->flushAndClear();
            }
        }

        $this->flushAndClear();
    }

    private function hasDrifted(CatalogObject $product): bool
    {
        $snapshot = $product->getSchemaSnapshot();
        if (null === $snapshot) {
            return false; // no baseline captured — nothing to compare against
        }

        $snapshotIds = [];
        $raw = $snapshot['attributeGroupIds'] ?? null;
        if (\is_array($raw)) {
            foreach ($raw as $value) {
                if (\is_string($value)) {
                    $snapshotIds[] = $value;
                }
            }
        }

        $current = array_map(
            static fn (AttributeGroup $group): string => $group->getId()->toRfc4122(),
            $this->resolver->resolve($product),
        );

        sort($snapshotIds);
        sort($current);

        return $snapshotIds !== $current;
    }

    /**
     * RFC-4122 ids of products assigned to the moved category subtree.
     *
     * @return list<string>
     */
    private function affectedProductIds(string $tenantId, string $path): array
    {
        // tenant-safe: explicit tenant_id filter on the joined category rows;
        // subtree via ltree `<@`.
        $rows = $this->entityManager->getConnection()->fetchFirstColumn(
            'SELECT DISTINCT oc.object_id FROM object_categories oc'
            .' JOIN objects c ON c.id = oc.category_id'
            .' WHERE c.tenant_id = CAST(:tenant AS uuid) AND c.path <@ CAST(:path AS ltree)',
            ['tenant' => $tenantId, 'path' => $path],
        );

        $ids = [];
        foreach ($rows as $row) {
            if (\is_string($row)) {
                $ids[] = $row;
            }
        }

        return $ids;
    }
}
