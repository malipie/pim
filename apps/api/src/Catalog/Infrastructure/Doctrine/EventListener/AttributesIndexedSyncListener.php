<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\EventListener;

use App\Catalog\Application\AttributesIndexedRebuilder;
use App\Catalog\Application\BulkContext;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectValue;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Uid\Uuid;

/**
 * Synchronous rebuild of `CatalogObject.attributes_indexed` +
 * `completeness` when an `ObjectValue` is persisted, updated, or
 * removed in a single-edit flow.
 *
 * Wires the cache through Doctrine's two-phase flush:
 *
 *   1. `onFlush` collects every CatalogObject id whose ObjectValue rows
 *      changed in this unit of work.
 *   2. `postFlush` issues a second flush that recomputes
 *      `attributes_indexed` + `completeness` on each collected object
 *      and persists them. The second flush is guarded by an in-flight
 *      flag so the listener does not recurse into itself.
 *
 * Bulk flows opt out via {@see BulkContext::isBulk()} — those dispatch
 * an asynchronous `ObjectValuesChangedMessage` (#38 worker, lands here
 * later) so the bulk-import handler keeps its `flush()` + `clear()`
 * cycle clean of N×rebuild work.
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class AttributesIndexedSyncListener
{
    /**
     * @var array<string, true>
     */
    private array $pendingObjectIds = [];

    private bool $rebuilding = false;

    public function __construct(
        private readonly BulkContext $bulkContext,
        private readonly AttributesIndexedRebuilder $rebuilder,
    ) {
    }

    public function onFlush(OnFlushEventArgs $event): void
    {
        if ($this->bulkContext->isBulk() || $this->rebuilding) {
            return;
        }

        $uow = $event->getObjectManager()->getUnitOfWork();

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            $this->collect($entity);
        }
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            $this->collect($entity);
        }
        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            $this->collect($entity);
        }
    }

    public function postFlush(PostFlushEventArgs $event): void
    {
        if ($this->bulkContext->isBulk() || $this->rebuilding || [] === $this->pendingObjectIds) {
            return;
        }

        $em = $event->getObjectManager();
        $ids = array_keys($this->pendingObjectIds);
        $this->pendingObjectIds = [];

        $this->rebuilding = true;
        try {
            foreach ($ids as $idString) {
                $object = $em->find(CatalogObject::class, Uuid::fromString($idString));
                if (!$object instanceof CatalogObject) {
                    continue;
                }
                $this->rebuilder->rebuild($object);
            }
            $em->flush();
        } finally {
            $this->rebuilding = false;
        }
    }

    private function collect(object $entity): void
    {
        if (!$entity instanceof ObjectValue) {
            return;
        }

        $this->pendingObjectIds[$entity->getObject()->getId()->toRfc4122()] = true;
    }
}
