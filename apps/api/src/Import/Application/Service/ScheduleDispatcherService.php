<?php

declare(strict_types=1);

namespace App\Import\Application\Service;

use App\Import\Domain\Entity\ImportSchedule;
use App\Import\Domain\Entity\ImportScheduleRun;
use App\Import\Domain\Enum\ScheduleRunStatus;
use App\Import\Domain\Repository\ImportScheduleRepositoryInterface;
use App\Import\Domain\Repository\ImportScheduleRunRepositoryInterface;
use DateTimeImmutable;

/**
 * VIEW-IMP-04 (#502) — manual dispatch + run audit.
 *
 * The cron worker daemon ships with VIEW-IMP-04.1. For V04 the
 * dispatcher exposes:
 *   - computeNextRun(): keep the schedule row up to date with the next
 *     fire time the FE timeline shows.
 *   - runNow(): synchronously stamp a run row, refresh `nextRun`, and
 *     leave the actual import-session creation to the integration the
 *     follow-up ticket wires up (we record `pending` so the operator
 *     can see the row immediately).
 */
final readonly class ScheduleDispatcherService
{
    public function __construct(
        private CronExpressionParser $parser,
        private ImportScheduleRepositoryInterface $schedules,
        private ImportScheduleRunRepositoryInterface $runs,
    ) {
    }

    public function computeNextRun(ImportSchedule $schedule, ?DateTimeImmutable $from = null): void
    {
        if (!$this->parser->isValid($schedule->getCron())) {
            $schedule->setNextRun(null);
            $this->schedules->save($schedule);

            return;
        }
        $schedule->setNextRun($this->parser->nextRun($schedule->getCron(), $from));
        $this->schedules->save($schedule);
    }

    public function runNow(ImportSchedule $schedule): ImportScheduleRun
    {
        $tenant = $schedule->getTenant();
        $run = new ImportScheduleRun(
            scheduleId: $schedule->getId(),
            status: ScheduleRunStatus::Pending,
        );
        if (null !== $tenant) {
            $run->assignTenant($tenant);
        }
        $this->runs->save($run);

        $schedule->recordRun(ScheduleRunStatus::Pending, new DateTimeImmutable(), null);
        $this->computeNextRun($schedule);

        return $run;
    }
}
