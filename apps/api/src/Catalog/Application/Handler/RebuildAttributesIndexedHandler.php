<?php

declare(strict_types=1);

namespace App\Catalog\Application\Handler;

use App\Catalog\Application\AttributesIndexedRebuilder;
use App\Catalog\Application\Message\ObjectValuesChangedMessage;
use App\Catalog\Application\Reindex\BulkReindexQueueInterface;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Shared\Application\AbstractBatchHandler;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
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
 *
 * IMP2-2.6 — also reindexes the batch in Meilisearch, AFTER the rebuild has
 * been flushed: the search document is built from `attributes_indexed`, so
 * queueing the reindex before the rebuild would index an empty/stale doc. With
 * BulkContext suppressing the per-flush sync indexer during the import, this is
 * the path that gets imported objects into search.
 */
#[AsMessageHandler]
final class RebuildAttributesIndexedHandler extends AbstractBatchHandler
{
    /** IMP2-2.9 (#1485) — attempts per object before giving up on a version conflict. */
    private const int MAX_REBUILD_RETRIES = 3;

    private readonly LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        private readonly AttributesIndexedRebuilder $rebuilder,
        private readonly BulkReindexQueueInterface $reindexQueue,
        private readonly ManagerRegistry $managerRegistry,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($entityManager);
        $this->logger = $logger ?? new NullLogger();
    }

    public function __invoke(ObjectValuesChangedMessage $message): void
    {
        // IMP2-2.9 (#1485) — rebuild + flush PER ID so a concurrent edit (UI bump
        // of objects.version between find and flush) only affects the conflicting
        // object: it is retried with a fresh read, instead of an
        // OptimisticLockException dead-lettering the whole batch (including the
        // objects already rebuilt).
        //
        // AUD-039 / G-01 — an object that exhausts its retries is NO LONGER
        // silently skipped: it is collected, the rest of the batch is finished
        // (the rebuild is idempotent, so a re-delivery re-runs the survivors as
        // cheap no-ops), and the handler throws at the end. The async retry
        // policy re-delivers the batch and, once exhausted, dead-letters it to
        // the `failed` transport — making drift loud instead of hiding it behind
        // a "successful" message.
        $reindexable = [];
        $failedIds = [];
        foreach ($message->objectIds as $idString) {
            if ($this->rebuildOneWithRetry(Uuid::fromString($idString), $idString)) {
                $reindexable[] = $idString;
            } else {
                $failedIds[] = $idString;
            }
        }

        // IMP2-2.6 — attributes_indexed is rebuilt + committed for the survivors;
        // reindex them in Meilisearch (reads the fresh attributes_indexed). Skip
        // the failed ids — their cache is stale and the re-delivery will reindex
        // them once rebuilt.
        if ([] !== $reindexable) {
            $this->reindexQueue->queueAll($reindexable);
        }

        if ([] !== $failedIds) {
            $this->logger->error(
                'attributes_indexed rebuild failed for {count} object(s) after exhausting version conflicts',
                ['object_ids' => $failedIds, 'count' => \count($failedIds)],
            );

            throw new AttributesIndexedRebuildFailedException($failedIds);
        }
    }

    private function rebuildOneWithRetry(Uuid $id, string $idString): bool
    {
        for ($attempt = 1; $attempt <= self::MAX_REBUILD_RETRIES; ++$attempt) {
            // Pull the manager fresh each attempt: a prior conflict reset it
            // (see catch), and the registry hands back the current open one.
            $em = $this->managerRegistry->getManager();
            \assert($em instanceof EntityManagerInterface);

            $object = $em->find(CatalogObject::class, $id);
            if (!$object instanceof CatalogObject) {
                // Object was deleted between the change event and this rebuild —
                // nothing to rebuild, nothing drifted; treat as a clean success.
                return true;
            }

            try {
                $this->rebuilder->rebuild($object);
                $em->flush();
                $em->clear();

                return true;
            } catch (OptimisticLockException) {
                // A concurrent edit bumped objects.version between find and
                // flush. Doctrine's UnitOfWork::commit closes the EM on a failed
                // commit (its `finally` calls em->close()), so clear() alone
                // cannot recover — the next flush would hit EntityManagerClosed.
                // resetManager() swaps in a fresh, open manager (the lazy
                // service proxy now points at it) so the retry reads + flushes
                // cleanly.
                $this->managerRegistry->resetManager();

                if ($attempt >= self::MAX_REBUILD_RETRIES) {
                    // AUD-039 — exhausted: report failure to the caller so the
                    // batch can be dead-lettered. No more silent return.
                    return false;
                }
            }
        }

        return false;
    }
}
