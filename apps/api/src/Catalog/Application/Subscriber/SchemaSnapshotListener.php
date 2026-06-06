<?php

declare(strict_types=1);

namespace App\Catalog\Application\Subscriber;

use App\Catalog\Application\Service\SchemaSnapshotFactory;
use App\Catalog\Contracts\Event\ObjectAttributesChanged;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\ObjectKind;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * CHC-04 (#1288) — captures a product's effective attribute-group set the first
 * time its values are filled. Synchronous + idempotent: writes the snapshot
 * only when none exists yet, so it never overwrites the baseline the async
 * drift check ({@see \App\Catalog\Application\Handler\CheckSchemaDriftHandler})
 * compares against after a category move.
 *
 * Listens to {@see ObjectAttributesChanged} (already emitted on every
 * `attributes_indexed` write). Snapshotting `schema_snapshot` does not emit
 * that event, so there is no re-trigger loop.
 */
#[AsMessageHandler]
final class SchemaSnapshotListener
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SchemaSnapshotFactory $snapshots,
    ) {
    }

    public function __invoke(ObjectAttributesChanged $event): void
    {
        $object = $this->em->find(CatalogObject::class, $event->objectId);
        if (!$object instanceof CatalogObject || ObjectKind::Product !== $object->getKind()) {
            return;
        }
        if (null !== $object->getSchemaSnapshot()) {
            return; // baseline already captured — keep it
        }

        $object->recordSchemaSnapshot($this->snapshots->build($object));
        $this->em->flush();
    }
}
