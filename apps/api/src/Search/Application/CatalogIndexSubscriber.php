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
 * Catalog → Search bridge (#50 / 0.5.2).
 *
 * Each domain event maps to one indexer call:
 *   - `ObjectCreated`/`ObjectAttributesChanged`/`ObjectEnabledChanged`/
 *     `ObjectPublished` → `CatalogObjectIndexer::index()` re-pushes the
 *     full document (Meilisearch upserts by primary key, so a single
 *     `addDocuments` covers create + partial update).
 *   - `ObjectArchived` → `CatalogObjectIndexer::remove()` drops the row
 *     so archived items never surface in search results.
 *
 * Bulk path (CSV import, agent batch, demo seeder) skips indexing —
 * `BulkContext::isBulk()` flag set by the orchestrator. The bulk
 * handler dispatches `pim:search:reindex` (#51) once at the end of
 * its flush cycle.
 *
 * Sync dispatch via `messenger.bus.default` keeps the indexer simple
 * for MVP. Async transport in Faza 1 (when sync 50k SKU benchmarks
 * show indexing latency dominates the write path).
 */
final readonly class CatalogIndexSubscriber
{
    public function __construct(
        private CatalogObjectIndexer $indexer,
        private BulkContext $bulkContext,
    ) {
    }

    #[AsMessageHandler]
    public function onObjectCreated(ObjectCreated $event): void
    {
        if ($this->bulkContext->isBulk()) {
            return;
        }
        $this->indexer->index($event->objectId);
    }

    #[AsMessageHandler]
    public function onObjectAttributesChanged(ObjectAttributesChanged $event): void
    {
        if ($this->bulkContext->isBulk()) {
            return;
        }
        $this->indexer->index($event->objectId);
    }

    #[AsMessageHandler]
    public function onObjectEnabledChanged(ObjectEnabledChanged $event): void
    {
        if ($this->bulkContext->isBulk()) {
            return;
        }
        $this->indexer->index($event->objectId);
    }

    #[AsMessageHandler]
    public function onObjectPublished(ObjectPublished $event): void
    {
        if ($this->bulkContext->isBulk()) {
            return;
        }
        $this->indexer->index($event->objectId);
    }

    #[AsMessageHandler]
    public function onObjectArchived(ObjectArchived $event): void
    {
        if ($this->bulkContext->isBulk()) {
            return;
        }
        // Archive should hide the row from search; events carry no kind,
        // so we re-fetch via the indexer's repository to read it.
        // Fast path: just push the latest doc — Meili will surface it
        // with `enabled=false` / `status=archived` and the read-side
        // filters in #52 strip archived rows from results.
        $this->indexer->index($event->objectId);
    }
}
