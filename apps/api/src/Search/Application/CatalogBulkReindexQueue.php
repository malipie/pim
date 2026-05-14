<?php

declare(strict_types=1);

namespace App\Search\Application;

use App\Catalog\Application\Reindex\BulkReindexQueueInterface;
use App\Catalog\Domain\ObjectKind;
use Symfony\Component\Uid\Uuid;

/**
 * Search-side adapter for {@see BulkReindexQueueInterface}. Bridges
 * bulk handlers (which run with `BulkContext::isBulk()=true` so the
 * per-event {@see CatalogIndexSubscriber} short-circuits) into the
 * shared {@see CatalogIndexCollector}.
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
 * Bulk handlers depend on {@see BulkReindexQueueInterface} from the
 * Catalog namespace; Symfony DI autowires it to this adapter so the
 * dependency direction stays `Search → Catalog_Internals` (allowed)
 * instead of `Catalog_Internals → Search` (rejected by deptrac).
 */
final readonly class CatalogBulkReindexQueue implements BulkReindexQueueInterface
{
    public function __construct(
        private CatalogIndexCollector $collector,
    ) {
    }

    public function queueAll(iterable $idsRfc4122): void
    {
        foreach ($idsRfc4122 as $id) {
            if ('' === $id) {
                continue;
            }
            $this->collector->queueUpsert(Uuid::fromString($id));
        }
    }

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
