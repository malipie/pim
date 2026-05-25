<?php

declare(strict_types=1);

namespace App\Search\Application;

use App\Catalog\Domain\ObjectKind;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Service\ResetInterface;

/**
 * PROD-03 — request-scoped buffer of CatalogObject ids that need a
 * Meilisearch refresh.
 *
 * The single-edit path used to call {@see CatalogObjectIndexer::index()}
 * per domain event, which translated into one HTTP round-trip per
 * affected row. Variant generation (16 axes) and category cascades
 * (descendant moves) blew that up to N×Meili requests per HTTP call.
 *
 * This collector dedupes by object id (multiple events for the same
 * object collapse to a single upsert) and partitions deletes by kind
 * because the indexer needs the kind to pick the destination index. A
 * companion {@see CatalogIndexFlushSubscriber} drains the buffer on
 * `kernel.terminate` so the response is already on the wire before we
 * hit Meilisearch.
 *
 * Singleton service with `ResetInterface` so the FrankenPHP worker
 * cannot leak buffered ids between requests.
 */
final class CatalogIndexCollector implements ResetInterface
{
    /** @var array<string, true> objectId (RFC4122 string) => true */
    private array $upserts = [];

    /** @var array<string, ObjectKind> objectId (RFC4122 string) => kind (telemetry only post-ULV) */
    private array $deletes = [];

    public function queueUpsert(Uuid $id): void
    {
        $rfc = $id->toRfc4122();
        // If a delete was queued earlier in the same request and now an
        // upsert lands, the object is being recreated/re-published — drop
        // the stale delete so the final state is the upsert.
        unset($this->deletes[$rfc]);
        $this->upserts[$rfc] = true;
    }

    /**
     * `$kind` is retained as a legacy hint (telemetry, logging). ULV-02
     * (#983) consolidated the per-kind indexes into a single `objects`
     * index, so the collector no longer partitions deletes by kind.
     */
    public function queueDelete(Uuid $id, ObjectKind $kind): void
    {
        $rfc = $id->toRfc4122();
        // Delete supersedes a pending upsert in the same request — Meili
        // would reject the upsert for a row that is about to vanish anyway.
        unset($this->upserts[$rfc]);
        $this->deletes[$rfc] = $kind;
    }

    public function isEmpty(): bool
    {
        return [] === $this->upserts && [] === $this->deletes;
    }

    /**
     * @return list<string>
     */
    public function drainUpsertIds(): array
    {
        $ids = array_keys($this->upserts);
        $this->upserts = [];

        return $ids;
    }

    /**
     * @return array<string, ObjectKind>
     */
    public function drainDeletes(): array
    {
        $deletes = $this->deletes;
        $this->deletes = [];

        return $deletes;
    }

    public function reset(): void
    {
        $this->upserts = [];
        $this->deletes = [];
    }
}
