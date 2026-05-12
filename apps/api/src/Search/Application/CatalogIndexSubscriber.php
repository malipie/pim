<?php

declare(strict_types=1);

namespace App\Search\Application;

use App\Catalog\Application\BulkContext;
use App\Catalog\Contracts\Event\ObjectArchived;
use App\Catalog\Contracts\Event\ObjectAttributesChanged;
use App\Catalog\Contracts\Event\ObjectCreated;
use App\Catalog\Contracts\Event\ObjectEnabledChanged;
use App\Catalog\Contracts\Event\ObjectPublished;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Catalog → Search bridge (#50 / 0.5.2, batch-extended in PROD-03).
 *
 * Each domain event used to call the indexer directly which translated
 * into one Meilisearch HTTP request per affected row. Variant generation
 * (16 axes) and category cascades (descendant moves) blew that up to
 * N×Meili requests per HTTP call. PROD-03 routes events through a
 * request-scoped {@see CatalogIndexCollector}; the matching
 * {@see CatalogIndexFlushSubscriber} drains the buffer on
 * `kernel.terminate` so Meili sees one batched call per kind per
 * request, after the response is on the wire.
 *
 * Bulk path (CSV import, agent batch, demo seeder) still skips entirely
 * via {@see BulkContext::isBulk()} — the bulk handler dispatches
 * `pim:search:reindex` (#51) once at the end of its flush cycle, so
 * routing those events through the collector would just buffer ids that
 * the bulk reindex covers anyway.
 *
 * Sync dispatch via `messenger.bus.default` keeps the subscriber
 * simple; the only async hop is the deferred Meili push on terminate.
 */
final readonly class CatalogIndexSubscriber
{
    public function __construct(
        private CatalogIndexCollector $collector,
        private BulkContext $bulkContext,
    ) {
    }

    #[AsMessageHandler]
    public function onObjectCreated(ObjectCreated $event): void
    {
        if ($this->bulkContext->isBulk()) {
            return;
        }
        $this->collector->queueUpsert($event->objectId);
    }

    #[AsMessageHandler]
    public function onObjectAttributesChanged(ObjectAttributesChanged $event): void
    {
        if ($this->bulkContext->isBulk()) {
            return;
        }
        $this->collector->queueUpsert($event->objectId);
    }

    #[AsMessageHandler]
    public function onObjectEnabledChanged(ObjectEnabledChanged $event): void
    {
        if ($this->bulkContext->isBulk()) {
            return;
        }
        $this->collector->queueUpsert($event->objectId);
    }

    #[AsMessageHandler]
    public function onObjectPublished(ObjectPublished $event): void
    {
        if ($this->bulkContext->isBulk()) {
            return;
        }
        $this->collector->queueUpsert($event->objectId);
    }

    #[AsMessageHandler]
    public function onObjectArchived(ObjectArchived $event): void
    {
        if ($this->bulkContext->isBulk()) {
            return;
        }
        // Archive should hide the row from search. The event carries no
        // kind, so we re-push the latest doc — Meili will surface it with
        // `enabled=false` / `status=archived` and the read-side filters in
        // #52 strip archived rows from results.
        $this->collector->queueUpsert($event->objectId);
    }
}
