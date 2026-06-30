<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Generic\Application;

use App\Integration\Generic\Application\ConnectionProbeOutcome;
use App\Integration\Generic\Domain\Enum\ConnectionStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * #1890 — a base-URL probe must not flag a reachable host as failed just because
 * the bare base returns a non-2xx code (e.g. IdoSell `/api/admin/v5` → 404).
 */
final class ConnectionProbeOutcomeTest extends TestCase
{
    #[DataProvider('cases')]
    #[Test]
    public function mapsHttpStatusToReachability(
        int $httpStatus,
        ConnectionStatus $expectedStatus,
        bool $expectedReachable,
        bool $expectNote,
    ): void {
        $outcome = ConnectionProbeOutcome::fromHttpStatus($httpStatus);

        self::assertSame($expectedStatus, $outcome->status);
        self::assertSame($expectedReachable, $outcome->reachable);
        self::assertSame($expectNote, null !== $outcome->note);
    }

    /**
     * @return iterable<string, array{int, ConnectionStatus, bool, bool}>
     */
    public static function cases(): iterable
    {
        yield '200 OK → active, no note' => [200, ConnectionStatus::Active, true, false];
        yield '204 → active' => [204, ConnectionStatus::Active, true, false];
        yield '404 base path → active + note' => [404, ConnectionStatus::Active, true, true];
        yield '500 reachable → active + note' => [500, ConnectionStatus::Active, true, true];
        yield '301 redirect → active + note' => [301, ConnectionStatus::Active, true, true];
        yield '401 auth → error' => [401, ConnectionStatus::Error, false, true];
        yield '403 forbidden → error' => [403, ConnectionStatus::Error, false, true];
    }
}
