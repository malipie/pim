<?php

declare(strict_types=1);

namespace App\Shared\Application;

use App\Shared\Domain\Tenant;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

/**
 * PROD-05 — at-most-one bulk operation per tenant.
 *
 * Bulk handlers (import, attributes_indexed rebuild, snapshot) hold the
 * Postgres connection pool, the Meilisearch index, and the FrankenPHP
 * worker memory budget for the duration of a job. Two concurrent bulk
 * jobs from the same tenant — same operator clicking import twice, two
 * operators within the same org — multiply that footprint with no
 * matching benefit and create races on the per-row `attributes_indexed`
 * write path.
 *
 * Implementation: Symfony Lock keyed `bulk-op:{tenantId}`. Non-blocking
 * acquire so a contending handler returns immediately and the caller
 * decides whether to retry (Messenger {@see RecoverableMessageHandlingException})
 * or surface a domain-specific status (`ImportSession::pause`).
 *
 * TTL is set to 1h: long enough for the largest realistic single-tenant
 * import (5k SKU at 50 ms/row ≈ 4 min, with headroom for outliers); short
 * enough that a crashed worker frees the lock before it blocks the
 * tenant's next operation past the operator's patience.
 *
 * Backend swaps via {@see LOCK_DSN} env: `flock` for single-container dev,
 * `redis://redis:6379` in prod so the lock is shared across the API
 * pool + Messenger worker pool. Configured in docker-compose.prod.yml
 * (PROD-05 overlay extension).
 */
final readonly class BulkOperationLock
{
    /**
     * Auto-release after 1h to recover from a crashed worker that did
     * not run its `finally { release() }` block. {@see LockInterface}'s
     * heartbeat extends the TTL while the holder process is alive.
     */
    private const float TTL_SECONDS = 3600.0;

    public function __construct(
        private LockFactory $lockFactory,
    ) {
    }

    /**
     * @return LockInterface|null null when another worker already holds
     *                            the lock for this tenant; never blocks
     */
    public function acquire(Tenant $tenant): ?LockInterface
    {
        $lock = $this->lockFactory->createLock(
            self::keyFor($tenant),
            ttl: self::TTL_SECONDS,
            autoRelease: true,
        );

        if (false === $lock->acquire(blocking: false)) {
            return null;
        }

        return $lock;
    }

    public static function keyFor(Tenant $tenant): string
    {
        return 'bulk-op:'.$tenant->getId()->toRfc4122();
    }
}
