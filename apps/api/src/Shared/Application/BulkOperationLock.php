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
            // Flock-backed locks survive a PHP fatal that bypasses
            // `finally { release() }`. The OS releases the file lock
            // on process exit, but FrankenPHP's worker mode keeps the
            // same process alive across requests, so a request that
            // hit `max_execution_time` (e.g. a 30s bulk handler with
            // no `set_time_limit(0)` opt-out) holds the flock until
            // the worker is recycled. Pre-`set_time_limit` runs left
            // stale locks that blocked the next bulk action for the
            // full 1h TTL. As a safety net: when the lock file is
            // older than the TTL, treat it as abandoned, unlink, and
            // retry once. New holders rotate the file on acquire so
            // a healthy concurrent holder won't trip this branch.
            $this->forceClearStaleLockFile($tenant);
            // PHPStan can't see past the first `acquire()` call here
            // because `LockInterface::acquire(blocking: false)` is
            // typed as a closed boolean. Suppressed manually — at
            // runtime the second call can succeed once the stale
            // file got unlinked above.
            // @phpstan-ignore booleanNot.alwaysTrue
            if (!$lock->acquire(blocking: false)) {
                return null;
            }
        }

        return $lock;
    }

    private function forceClearStaleLockFile(Tenant $tenant): void
    {
        $matches = glob(sys_get_temp_dir().'/sf.'.self::keyFor($tenant).'.*.lock');
        if (!\is_array($matches)) {
            return;
        }
        $threshold = time() - (int) self::TTL_SECONDS;
        foreach ($matches as $path) {
            $mtime = @filemtime($path);
            if (false !== $mtime && $mtime < $threshold) {
                @unlink($path);
            }
        }
    }

    public static function keyFor(Tenant $tenant): string
    {
        return 'bulk-op:'.$tenant->getId()->toRfc4122();
    }
}
