<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Generic\Application\Schedule;

use App\Integration\Generic\Application\Schedule\SyncScheduleCalculator;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SyncScheduleCalculator::class)]
final class SyncScheduleCalculatorTest extends TestCase
{
    private function utc(string $at): DateTimeImmutable
    {
        return new DateTimeImmutable($at, new DateTimeZone('UTC'));
    }

    public function testIsValidAcceptsAndRejects(): void
    {
        $calc = new SyncScheduleCalculator();

        self::assertTrue($calc->isValid('0 2 * * *'));
        self::assertTrue($calc->isValid('*/5 * * * *'));
        self::assertFalse($calc->isValid('not a cron'));
        self::assertFalse($calc->isValid('99 99 * * *'));
    }

    public function testNextRunComputesTheNextDailySlot(): void
    {
        $calc = new SyncScheduleCalculator();

        $next = $calc->nextRun('0 2 * * *', $this->utc('2026-06-29 12:00:00'));

        self::assertSame('2026-06-30 02:00:00', $next->format('Y-m-d H:i:s'));
    }

    public function testNextRunComputesTheNextFiveMinuteSlot(): void
    {
        $calc = new SyncScheduleCalculator();

        $next = $calc->nextRun('*/5 * * * *', $this->utc('2026-06-29 12:01:00'));

        self::assertSame('2026-06-29 12:05:00', $next->format('Y-m-d H:i:s'));
    }

    public function testJitterIsDeterministicAndBounded(): void
    {
        $calc = new SyncScheduleCalculator(maxJitterSeconds: 300);
        $seed = 'tenant-abc';

        $first = $calc->jitterSeconds($seed);
        $second = $calc->jitterSeconds($seed);

        self::assertSame($first, $second, 'same seed must yield the same offset');
        self::assertGreaterThanOrEqual(0, $first);
        self::assertLessThanOrEqual(300, $first);
    }

    public function testJitterDisabledWhenWindowIsZero(): void
    {
        $calc = new SyncScheduleCalculator(maxJitterSeconds: 0);

        self::assertSame(0, $calc->jitterSeconds('any-seed'));
    }

    public function testJitterSpreadsAcrossSeeds(): void
    {
        $calc = new SyncScheduleCalculator(maxJitterSeconds: 300);

        $offsets = [];
        foreach (['t-1', 't-2', 't-3', 't-4', 't-5', 't-6', 't-7', 't-8'] as $seed) {
            $offsets[] = $calc->jitterSeconds($seed);
        }

        // AC-2: the jitter must actually disperse starts — not collapse every
        // tenant onto one offset.
        self::assertGreaterThan(1, \count(array_unique($offsets)));
    }

    public function testNextRunWithJitterOffsetsTheBaseSlot(): void
    {
        $calc = new SyncScheduleCalculator(maxJitterSeconds: 300);
        $from = $this->utc('2026-06-29 12:00:00');
        $seed = 'tenant-xyz';

        $base = $calc->nextRun('0 2 * * *', $from);
        $jittered = $calc->nextRunWithJitter('0 2 * * *', $seed, $from);

        $expected = $base->modify(\sprintf('+%d seconds', $calc->jitterSeconds($seed)));
        self::assertEquals($expected, $jittered);
        self::assertGreaterThanOrEqual($base, $jittered);
    }

    public function testNextRunWithJitterEqualsBaseWhenWindowIsZero(): void
    {
        $calc = new SyncScheduleCalculator(maxJitterSeconds: 0);
        $from = $this->utc('2026-06-29 12:00:00');

        $base = $calc->nextRun('0 2 * * *', $from);
        $jittered = $calc->nextRunWithJitter('0 2 * * *', 'tenant-xyz', $from);

        self::assertEquals($base, $jittered);
    }
}
