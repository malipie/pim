<?php

declare(strict_types=1);

namespace App\Search\Application;

use App\Catalog\Domain\ObjectKind;
use Symfony\Component\Uid\Uuid;

/**
 * Bridge from bulk handlers (which run with `BulkContext::isBulk()=true`
 * so the per-event {@see CatalogIndexSubscriber} short-circuits) into
 * the shared {@see CatalogIndexCollector}.
 *
 * VIEW-12 carried an unstated assumption that bulk handlers would call
 * `pim:search:reindex` at the end of their run — fine for CSV imports
 * that drain hundreds of thousands of rows, but heavy-handed for a
 * wizard click that touches 20 SKUs. The middle ground is to push the
 * affected ids into the request-scoped collector here so the existing
 * {@see CatalogIndexFlushSubscriber} drains them on `kernel.terminate`
 * — one batched Meili call per request, dedupe for free, no command
 * dispatch needed.
 *
 * Bulk handlers receive this service instead of the collector directly
 * because:
 *   - It documents the intent (this is the post-bulk reindex path,
 *     not generic queueing).
 *   - The collector lives in the Search bundle's internal namespace;
 *     the queue exposes the minimal surface area handlers need
 *     (`queueAll(list<string>)`).
 */
final readonly class BulkReindexQueue
{
    public function __construct(
        private CatalogIndexCollector $collector,
    ) {
    }

    /**
     * @param iterable<string> $idsRfc4122 catalog object ids touched by the bulk run
     */
    public function queueAll(iterable $idsRfc4122): void
    {
        foreach ($idsRfc4122 as $id) {
            if ('' === $id) {
                continue;
            }
            $this->collector->queueUpsert(Uuid::fromString($id));
        }
    }

    /**
     * Bulk delete companion — used by BulkDeleteHandler so the affected
     * Meili documents are removed in the same kernel.terminate flush.
     *
     * @param iterable<string> $idsRfc4122
     */
    public function queueAllDeleted(iterable $idsRfc4122, ObjectKind $kind): void
    {
        foreach ($idsRfc4122 as $id) {
            if ('' === $id) {
                continue;
            }
            $this->collector->queueDelete(Uuid::fromString($id), $kind);
        }
    }
}
