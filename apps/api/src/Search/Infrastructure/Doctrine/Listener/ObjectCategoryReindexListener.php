<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\Doctrine\Listener;

use App\Catalog\Application\BulkContext;
use App\Catalog\Domain\Entity\ObjectCategory;
use App\Search\Application\CatalogIndexCollector;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;

/**
 * VIEW-38 follow-up — queue the affected product for reindex whenever
 * an `ObjectCategory` row is added, moved (primary flag) or removed.
 *
 * Without this hook the denormalized `category[]` array on the Meili
 * product doc (built in {@see \App\Search\Application\CatalogObjectIndexer})
 * stayed frozen at whatever state the row had at the previous
 * `ObjectAttributesChanged`-driven reindex. Operators saw products
 * still match the *„Bez kategorii"* preset after assigning a category
 * because Meili was answering with the stale denormalization.
 *
 * Routing through {@see CatalogIndexCollector} reuses the existing
 * dedupe + kernel.terminate batching, so a multi-row category PATCH
 * (e.g. `replaceForProduct` wiping + re-inserting) still produces a
 * single Meili call.
 *
 * Bulk path (CSV import, demo seeder, BulkAddCategoryHandler) skips
 * via {@see BulkContext::isBulk()} — the bulk pipeline reindexes once
 * after its own flush cycle, so per-row queues here would just buffer
 * ids the bulk reindex covers anyway.
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
final class ObjectCategoryReindexListener
{
    public function __construct(
        private readonly CatalogIndexCollector $collector,
        private readonly BulkContext $bulkContext,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->queueOwningProduct($args->getObject());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->queueOwningProduct($args->getObject());
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $this->queueOwningProduct($args->getObject());
    }

    private function queueOwningProduct(object $entity): void
    {
        if (!$entity instanceof ObjectCategory) {
            return;
        }
        if ($this->bulkContext->isBulk()) {
            return;
        }
        $this->collector->queueUpsert($entity->getProduct()->getId());
    }
}
