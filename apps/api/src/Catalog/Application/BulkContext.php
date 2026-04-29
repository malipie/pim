<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use Symfony\Contracts\Service\ResetInterface;

/**
 * Toggle that bulk handlers flip ON to opt the synchronous Doctrine
 * listeners ({@see \App\Catalog\Infrastructure\Doctrine\EventListener\AttributesIndexedSyncListener},
 * {@see \App\Catalog\Infrastructure\Doctrine\EventListener\CompletenessRecalculator})
 * out of every persist + flush cycle.
 *
 * Per CLAUDE.md "Domain modeling" + lessons from #13: synchronous
 * listeners running per-row turn a 50k SKU bulk import into a 50k×listener
 * walk that detaches the catalog. Bulk workers set the flag, do their
 * persist + flush cycle in batches, and dispatch a `ObjectValuesChangedMessage`
 * for the asynchronous `RebuildAttributesIndexedHandler` which rebuilds
 * `attributes_indexed` + `completeness_pct` once per affected object.
 *
 * Default state is `false` — single-edit flows (admin UI, REST API)
 * leave the listeners on. The flag is request-scoped (Symfony service
 * is request-shared by default), so a misbehaving bulk run cannot leak
 * into a follow-up admin request.
 */
final class BulkContext implements ResetInterface
{
    private bool $bulk = false;

    public function setBulk(bool $bulk): void
    {
        $this->bulk = $bulk;
    }

    public function isBulk(): bool
    {
        return $this->bulk;
    }

    /**
     * Worker-mode safety: the singleton BulkContext gets reset between
     * requests so an early-terminated bulk job does not leave the flag on
     * for the next caller's listeners.
     */
    public function reset(): void
    {
        $this->bulk = false;
    }
}
