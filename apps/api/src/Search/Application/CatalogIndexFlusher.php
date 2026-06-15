<?php

declare(strict_types=1);

namespace App\Search\Application;

/**
 * Drains the {@see CatalogIndexCollector} into Meilisearch in one batched
 * `addDocuments` / `deleteDocuments` per kind. Extracted from
 * {@see CatalogIndexFlushSubscriber} (IMP2-2.6) so the same drain path is
 * shared by the HTTP/console terminate listener AND the worker drain
 * middleware ({@see CatalogIndexDrainMiddleware}).
 *
 * Failures are absorbed inside {@see CatalogObjectIndexer} — a Meili outage
 * must never bubble out of a terminate hook / message finally.
 *
 * The caller is responsible for ordering: the indexer reads each object's
 * `attributes_indexed` through the tenant filter, so flush() must run while a
 * tenant is bound and after the rebuild has been committed.
 */
final readonly class CatalogIndexFlusher
{
    public function __construct(
        private CatalogIndexCollector $collector,
        private CatalogObjectIndexer $indexer,
    ) {
    }

    public function flush(): void
    {
        if ($this->collector->isEmpty()) {
            return;
        }

        $upsertIds = $this->collector->drainUpsertIds();
        if ([] !== $upsertIds) {
            $this->indexer->indexBatch($upsertIds);
        }

        $deletes = $this->collector->drainDeletes();
        if ([] !== $deletes) {
            $this->indexer->removeBatch($deletes);
        }
    }
}
