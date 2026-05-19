<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Infrastructure\Audit;

use App\Identity\Application\Audit\AuditLogRequestMapper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * RBAC-P3-013 (#676) — unit coverage of {@see AuditLogRequestMapper}
 * (the pure helpers used by the listener). Full ResponseEvent wiring
 * lives in the integration suite (Phase 6 retrofit follow-up).
 */
final class AuditLogListenerTest extends TestCase
{
    #[Test]
    public function resolvePermissionCheckResultMapsHttpStatuses(): void
    {
        $mapper = new AuditLogRequestMapper();

        self::assertSame('granted', $mapper->resolvePermissionCheckResult(200));
        self::assertSame('granted', $mapper->resolvePermissionCheckResult(201));
        self::assertSame('granted', $mapper->resolvePermissionCheckResult(204));
        self::assertSame('denied', $mapper->resolvePermissionCheckResult(403));
        self::assertSame('n_a', $mapper->resolvePermissionCheckResult(401));
        self::assertSame('n_a', $mapper->resolvePermissionCheckResult(404));
        self::assertSame('n_a', $mapper->resolvePermissionCheckResult(500));
    }

    #[Test]
    public function resolveResourceIdReadsKnownAttributeKeys(): void
    {
        $mapper = new AuditLogRequestMapper();

        self::assertSame('abc', $mapper->resolveResourceId(['id' => 'abc']));
        self::assertSame('cool-product', $mapper->resolveResourceId(['slug' => 'cool-product']));
        self::assertSame('CODE', $mapper->resolveResourceId(['code' => 'CODE']));
        self::assertSame('uuid-x', $mapper->resolveResourceId(['uuid' => 'uuid-x']));
    }

    #[Test]
    public function resolveResourceIdReturnsNullWhenNoKnownKey(): void
    {
        $mapper = new AuditLogRequestMapper();

        self::assertNull($mapper->resolveResourceId([]));
        self::assertNull($mapper->resolveResourceId(['_route' => 'app_route', 'foo' => 'bar']));
    }

    #[Test]
    public function resolveResourceIdSkipsNonScalarValues(): void
    {
        $mapper = new AuditLogRequestMapper();

        self::assertNull($mapper->resolveResourceId(['id' => ['array']]));
        self::assertNull($mapper->resolveResourceId(['id' => new stdClass()]));
    }

    #[Test]
    public function shouldSkipMatchesAllConfiguredPrefixes(): void
    {
        $mapper = new AuditLogRequestMapper();

        self::assertTrue($mapper->shouldSkip('/_wdt/abc'));
        self::assertTrue($mapper->shouldSkip('/_profiler/123'));
        self::assertTrue($mapper->shouldSkip('/health'));
        self::assertTrue($mapper->shouldSkip('/assets/main.js'));
        self::assertTrue($mapper->shouldSkip('/.well-known/mercure'));
        self::assertFalse($mapper->shouldSkip('/api/products'));
        self::assertFalse($mapper->shouldSkip('/api/auth/me'));
    }
}
