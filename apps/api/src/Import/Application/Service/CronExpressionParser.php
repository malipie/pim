<?php

declare(strict_types=1);

namespace App\Import\Application\Service;

use Cron\CronExpression;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * VIEW-IMP-04 (#502) — thin wrapper over `dragonmantank/cron-expression`.
 *
 * Centralises parsing + next-run computation so the rest of the stack
 * (UI preview, dispatcher) talks to a stable interface even if we swap
 * the cron library later.
 */
final class CronExpressionParser
{
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

        // dragonmantank/cron-expression returns DateTime — convert to immutable.
        return DateTimeImmutable::createFromMutable($cron->getNextRunDate($reference));
    }

    /**
     * @return list<DateTimeImmutable>
     */
    public function nextRuns(string $expression, int $count = 5, ?DateTimeImmutable $from = null): array
    {
        $cron = new CronExpression($expression);
        $reference = $from ?? new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $runs = [];
        foreach ($cron->getMultipleRunDates($count, $reference) as $run) {
            $runs[] = DateTimeImmutable::createFromMutable($run);
        }

        return $runs;
    }

    /**
     * Best-effort human-readable summary. Falls back to the raw cron
     * expression when the input is unusual — the FE renders both lines
     * anyway so the user keeps the original spec.
     */
    public function describe(string $expression): string
    {
        $parts = preg_split('/\s+/', trim($expression));
        if (false === $parts || \count($parts) !== 5) {
            return $expression;
        }
        [$minute, $hour, $dom, $month, $dow] = $parts;

        if ('*/5' === $minute && '*' === $hour && '*' === $dom && '*' === $month && '*' === $dow) {
            return 'co 5 minut';
        }
        if ('0' === $minute && '*' === $hour) {
            return 'co godzinę';
        }
        if ('0' === $minute && '*/2' === $hour) {
            return 'co 2 godziny';
        }
        if ('0' === $minute && 1 === preg_match('/^\d{1,2}$/', $hour) && '*' === $dom && '*' === $month && '*' === $dow) {
            return \sprintf('codziennie o %02d:00', (int) $hour);
        }
        if ('0' === $minute && 1 === preg_match('/^\d{1,2}$/', $hour) && '*' === $dom && '*' === $month && '1-5' === $dow) {
            return \sprintf('w dni robocze o %02d:00', (int) $hour);
        }

        return $expression;
    }
}
