<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Doctrine;

use App\Shared\Infrastructure\Doctrine\Middleware\StandardConformingStringsMiddleware;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Result;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * AUD-031 / W2-3 (C-2) — the middleware that guarantees
 * `standard_conforming_strings = on` on every physical connection (the
 * premise FilterDslResolver's single-quote escaping depends on).
 */
final class StandardConformingStringsMiddlewareTest extends TestCase
{
    #[Test]
    public function connectForcesTheSettingOnAndVerifiesIt(): void
    {
        $result = $this->createStub(Result::class);
        $result->method('fetchOne')->willReturn('on');

        // Mock (not stub) the connection — the SET + verification calls are
        // the behaviour under test, so they carry explicit expectations.
        $inner = $this->createMock(DriverConnection::class);
        $inner->expects(self::once())
            ->method('exec')
            ->with('SET standard_conforming_strings = on');
        $inner->expects(self::once())
            ->method('query')
            ->with('SHOW standard_conforming_strings')
            ->willReturn($result);

        $driver = $this->createStub(Driver::class);
        $driver->method('connect')->willReturn($inner);

        $middleware = new StandardConformingStringsMiddleware();
        $connection = $middleware->wrap($driver)->connect([]);

        // The wrapper transparently exposes the underlying connection.
        self::assertInstanceOf(DriverConnection::class, $connection);
    }

    #[Test]
    public function connectFailsLoudWhenTheServerRefusesTheSetting(): void
    {
        // If the server somehow reports the setting as `off` after the SET
        // (mis-set GUC, hostile config), the connection MUST fail loud rather
        // than silently allow FilterDSL escaping to leak.
        $result = $this->createStub(Result::class);
        $result->method('fetchOne')->willReturn('off');

        $inner = $this->createStub(DriverConnection::class);
        $inner->method('exec')->willReturn(0);
        $inner->method('query')->willReturn($result);

        $driver = $this->createStub(Driver::class);
        $driver->method('connect')->willReturn($inner);

        $middleware = new StandardConformingStringsMiddleware();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/standard_conforming_strings/');

        $middleware->wrap($driver)->connect([]);
    }
}
