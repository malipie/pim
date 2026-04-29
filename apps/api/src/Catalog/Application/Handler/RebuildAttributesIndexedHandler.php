<?php

declare(strict_types=1);

namespace App\Catalog\Application\Handler;

use App\Catalog\Application\AttributesIndexedRebuilder;
use App\Catalog\Application\Message\ObjectValuesChangedMessage;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Shared\Application\AbstractBatchHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Async rebuild of `attributes_indexed` + `completeness` for a batch of
 * CatalogObject ids. Inherits {@see AbstractBatchHandler}'s flush + clear
 * cadence so a 50k-id message does not OOM the FrankenPHP worker.
 *
 * Bulk import flows dispatch this from inside the BulkContext-true block;
 * the worker process picks it up after the request has returned, and the
 * sync listener stays out of the way the whole time (BulkContext is
 * request-scoped, so the worker's BulkContext is fresh-default-false —
 * the listener fires on the rebuilder's own flush, but the rebuilder
 * sets `attributes_indexed` directly on the entity so there is no
 * `ObjectValue` change to trigger a recursive rebuild).
 */
#[AsMessageHandler]
final class RebuildAttributesIndexedHandler extends AbstractBatchHandler
{
    public function __construct(
        EntityManagerInterface $entityManager,
        private readonly AttributesIndexedRebuilder $rebuilder,
    ) {
        parent::__construct($entityManager);
    }

    public function __invoke(ObjectValuesChangedMessage $message): void
    {
        foreach ($message->objectIds as $index => $idString) {
            $object = $this->entityManager->find(CatalogObject::class, Uuid::fromString($idString));
            if (!$object instanceof CatalogObject) {
                continue;
            }
            $this->rebuilder->rebuild($object);

            if ($this->shouldFlush($index + 1)) {
                $this->flushAndClear();
            }
        }

        // Tail flush — covers the last < batchSize chunk.
        $this->flushAndClear();
    }
}
