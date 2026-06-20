<?php

declare(strict_types=1);

namespace App\Catalog\Application\Bulk;

use App\Catalog\Application\BulkContext;
use App\Catalog\Application\Reindex\BulkReindexQueueInterface;
use App\Catalog\Domain\Entity\BulkLog;
use App\Catalog\Domain\Entity\BulkSession;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * Shared lifecycle for every per-object Bulk*Handler (AUD-056).
 *
 * The audit flagged 13.27 % copy-paste duplication across the Bulk
 * handlers — each one repeated the same ~45-line skeleton:
 *
 *   1. `set_time_limit(0)` so a 2k+ SKU run is not killed at PHP's 30s
 *      HTTP timeout mid-loop (which left the session incomplete);
 *   2. flip {@see BulkContext} ON in a try/finally that always flips it
 *      OFF so the synchronous indexed-attribute listeners stay opted-out
 *      for the whole run (CLAUDE.md FrankenPHP worker rule);
 *   3. iterate `BulkSession::getTargetObjectIds()`, resolve each id,
 *      run the per-object body inside a try/catch that converts an
 *      unexpected throwable into an error BulkLog row;
 *   4. flush + {@see EntityManagerInterface::clear()} every CHUNK_SIZE
 *      rows to cap Doctrine's identity map (worker memory rule);
 *   5. after the loop reload the BulkSession (the per-chunk clear()
 *      detached it + its Tenant proxy), `complete()` it with the tally,
 *      flush, and schedule the Meilisearch reindex.
 *
 * Only the per-object body and the reindex call ever differed. This base
 * owns 1–5; subclasses implement {@see processObject()} for the body and
 * override {@see reindex()} when they need the delete companion. The
 * mutable {@see BulkCounters} replaces the three local ints so a handler
 * can still tally a row however it likes (e.g. multi-attribute edit bumps
 * `skipped` per locked edit and `success` once for the row).
 *
 * Rollback (`BulkRollbackHandler`) deliberately does NOT extend this: it
 * walks `bulk_logs` rather than target ids and `markRolledBack()`s instead
 * of `complete()`, so it has its own loop.
 */
abstract class AbstractBulkHandler
{
    /**
     * Rows to mutate between flush + clear cycles. Override in a subclass
     * (e.g. BulkDuplicateHandler clones rows and uses a smaller chunk).
     */
    protected const int CHUNK_SIZE = 200;

    public function __construct(
        protected readonly CatalogObjectRepositoryInterface $catalogObjects,
        protected readonly EntityManagerInterface $em,
        protected readonly BulkContext $bulkContext,
        protected readonly BulkReindexQueueInterface $reindexQueue,
    ) {
    }

    /**
     * Apply the handler-specific mutation to a single resolved object,
     * persisting whatever BulkLog rows the rollback path needs and
     * updating the shared {@see BulkCounters}. Throwing here is caught by
     * {@see runBatch()} and recorded as an error row — but handlers
     * should prefer to record an explicit warning/error BulkLog and tally
     * the counter themselves for actionable per-row reporting.
     */
    abstract protected function processObject(CatalogObject $object, BulkSession $session, BulkCounters $counters): void;

    /**
     * Schedule the post-run search reindex for the touched ids. Defaults
     * to {@see BulkReindexQueueInterface::queueAll()}; BulkDeleteHandler
     * overrides to {@see BulkReindexQueueInterface::queueAllDeleted()}.
     */
    protected function reindex(BulkSession $session): void
    {
        $this->reindexQueue->queueAll($session->getTargetObjectIds());
    }

    /**
     * Run the shared bulk lifecycle over the session's target ids.
     *
     * @return array{success: int, skipped: int, error: int}
     */
    final protected function runBatch(BulkSession $session): array
    {
        // Long bulk runs (2k+ products) routinely exceed PHP's 30s HTTP
        // timeout — without disabling it the handler is killed mid-loop,
        // the session stays incomplete, and the operator sees 200 + zero
        // rows touched.
        set_time_limit(0);

        $this->bulkContext->setBulk(true, $session->getId());
        try {
            $counters = new BulkCounters();
            $chunkCounter = 0;

            foreach ($session->getTargetObjectIds() as $targetId) {
                try {
                    $object = $this->catalogObjects->findById(Uuid::fromString($targetId));
                    if (!$object instanceof CatalogObject) {
                        ++$counters->error;
                        ++$chunkCounter;
                        continue;
                    }

                    $this->processObject($object, $session, $counters);
                } catch (Throwable $e) {
                    ++$counters->error;
                    $this->em->persist(new BulkLog(
                        $session->getId(),
                        Uuid::fromString($targetId),
                        null,
                        null,
                        null,
                        BulkLog::LEVEL_ERROR,
                        $e->getMessage(),
                    ));
                }

                ++$chunkCounter;
                if ($chunkCounter >= static::CHUNK_SIZE) {
                    $this->em->flush();
                    $this->em->clear();
                    $chunkCounter = 0;
                }
            }

            if ($chunkCounter > 0) {
                $this->em->flush();
            }

            // Reload BulkSession: per-chunk em->clear() detached the
            // local instance and its Tenant proxy. The final persist
            // below would otherwise raise EntityNotFoundException on
            // flush trying to resolve the stale proxy.
            $reloaded = $this->em->find(BulkSession::class, $session->getId());
            if ($reloaded instanceof BulkSession) {
                $session = $reloaded;
            }

            $session->complete($counters->success, $counters->skipped, $counters->error);
            $this->em->persist($session);
            $this->em->flush();

            $this->reindex($session);

            return $counters->toResult();
        } finally {
            $this->bulkContext->setBulk(false);
        }
    }
}
