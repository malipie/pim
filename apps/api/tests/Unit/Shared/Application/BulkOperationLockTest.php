<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Application;

use App\Shared\Application\BulkOperationLock;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

/**
 * PROD-05 — confirms the per-tenant lock acquires once, blocks the
 * second attempt, and releases cleanly so the next call succeeds.
 *
 * In-memory store is used here so the test does not depend on a real
 * Redis or Postgres advisory store; the real-store behaviour is
 * exercised by the integration suite via the actual messenger handler
 * roundtrip.
 */
final class BulkOperationLockTest extends TestCase
{
    #[Test]
    public function acquireSucceedsOnceAndBlocksConcurrentSecondAttempt(): void
    {
        $factory = new LockFactory(new InMemoryStore());
        $lockService = new BulkOperationLock($factory);
        $tenant = new Tenant('demo', 'Demo');

        $first = $lockService->acquire($tenant);
        self::assertNotNull($first, 'first acquire must succeed');

        $second = $lockService->acquire($tenant);
        self::assertNull($second, 'second acquire on the same tenant must fail (non-blocking)');

        $first->release();

        $third = $lockService->acquire($tenant);
        self::assertNotNull($third, 'after release the next acquire must succeed');
        $third->release();
    }

    #[Test]
    public function differentTenantsHoldIndependentLocks(): void
    {
        $factory = new LockFactory(new InMemoryStore());
        $lockService = new BulkOperationLock($factory);

        $tenantA = new Tenant('a', 'Tenant A');
        $tenantB = new Tenant('b', 'Tenant B');

        $lockA = $lockService->acquire($tenantA);
        $lockB = $lockService->acquire($tenantB);

        self::assertNotNull($lockA);
        self::assertNotNull($lockB, 'different tenants must not contend on the same key');

        $lockA->release();
        $lockB->release();
    }

    #[Test]
    public function keyForReturnsStableTenantScopedKey(): void
    {
        $tenant = new Tenant('demo', 'Demo');
        $key = BulkOperationLock::keyFor($tenant);

        self::assertStringStartsWith('bulk-op:', $key);
        self::assertSame($key, BulkOperationLock::keyFor($tenant), 'key must be deterministic');
    }
}
