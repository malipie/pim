<?php

declare(strict_types=1);

namespace App\Catalog\Application\Reindex;

use App\Catalog\Domain\ObjectKind;

/**
 * Port used by bulk handlers to schedule a Meilisearch refresh for
 * every catalog object they touched. The implementation lives in the
 * Search bundle ({@see \App\Search\Application\CatalogBulkReindexQueue})
 * and pushes the ids into the request-scoped index collector so the
 * existing kernel.terminate flush ships them in one batched call.
 *
 * Why a port and not a direct dependency: deptrac forbids
 * `Catalog_Internals → Search` (Search is downstream of Catalog and
 * the catalog handlers must not know which read model they feed).
 * The interface lives in Catalog so handlers depend on their own
 * bounded context; Symfony DI binds the Search adapter at runtime.
 */
interface BulkReindexQueueInterface
{
    /**
     * @param iterable<string> $idsRfc4122 catalog object ids touched by the bulk run
     */
    public function queueAll(iterable $idsRfc4122): void;

    /**
     * Bulk delete companion — used by BulkDeleteHandler so the affected
     * Meili documents are removed in the same kernel.terminate flush.
     *
     * @param iterable<string> $idsRfc4122
     */
    public function queueAllDeleted(iterable $idsRfc4122, ObjectKind $kind): void;
}
