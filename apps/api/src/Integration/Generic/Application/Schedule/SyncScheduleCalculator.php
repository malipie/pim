<?php

declare(strict_types=1);

namespace App\Integration\Generic\Application\Schedule;

use Cron\CronExpression;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Next-run arithmetic for scheduled sync bindings (ADR-0022, epic APIC, ticket
 * APIC-P3-09). A thin wrapper over `dragonmantank/cron-expression` plus a
 * deterministic per-seed jitter.
 *
 * Reuses the cron *library* directly rather than `Import\…\CronExpressionParser`:
 * the Integration context may not depend on Import (ADR-0022 keeps cross-BC
 * coupling to Contracts), and the parser is itself only a wrapper over the same
 * vendor class.
 *
 * Jitter: every tenant gets a stable offset derived from its id, so 200 tenants
 * whose binding fires at `0 2 * * *` don't all hit the same remote API at
 * 02:00:00 — they spread across a {@see self::maxJitterSeconds}-wide window. The
 * offset is deterministic (a `crc32` of the seed), so a tenant's fire time stays
 * predictable run-to-run instead of drifting randomly.
 */
final readonly class SyncScheduleCalculator
{
    /**
     * @param int $maxJitterSeconds width of the spread window (default 5 min);
     *                              0 disables jitter
     */
    public function __construct(
        private int $maxJitterSeconds = 300,
    ) {
    }

    public function isValid(string $expression): bool
    {
        try {
            new CronExpression($expression);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function nextRun(string $expression, ?DateTimeImmutable $from = null): DateTimeImmutable
    {
        $cron = new CronExpression($expression);
        $reference = $from ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));

        return DateTimeImmutable::createFromMutable($cron->getNextRunDate($reference));
    }

    /**
     * The next fire time pushed forward by the seed's stable jitter offset.
     */
    public function nextRunWithJitter(string $expression, string $jitterSeed, ?DateTimeImmutable $from = null): DateTimeImmutable
    {
        $base = $this->nextRun($expression, $from);
        $offset = $this->jitterSeconds($jitterSeed);
        if (0 === $offset) {
            return $base;
        }

        return $base->modify(\sprintf('+%d seconds', $offset));
    }

    /**
     * Stable offset in `[0, maxJitterSeconds]` for a seed (typically a tenant id).
     */
    public function jitterSeconds(string $jitterSeed): int
    {
        if ($this->maxJitterSeconds <= 0) {
            return 0;
        }

        return crc32($jitterSeed) % ($this->maxJitterSeconds + 1);
    }
}
