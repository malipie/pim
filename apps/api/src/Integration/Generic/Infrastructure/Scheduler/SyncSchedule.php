<?php

declare(strict_types=1);

namespace App\Integration\Generic\Infrastructure\Scheduler;

use App\Shared\Infrastructure\Scheduler\RunMaintenanceCommand;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * APIC-P3-09 (ADR-0022, epic APIC) — the per-minute tick that fires due sync
 * bindings.
 *
 * Per-binding cron lives in the DB ({@see \App\Integration\Generic\Domain\Entity\SyncBinding}),
 * so a single recurring message can't enumerate them. Instead this schedule ticks
 * every minute and asks the worker to run `integration:sync:dispatch-due`, which
 * sweeps every tenant and enqueues the legs whose `nextRun` has arrived. Reuses
 * Shared's {@see RunMaintenanceCommand} runner (no per-command message class).
 *
 * Worker: the `scheduler_integration_sync` transport is auto-registered from the
 * `#[AsSchedule]` name and drained by `messenger:consume scheduler_integration_sync`
 * (docker-compose `worker`).
 */
#[AsSchedule('integration_sync')]
final class SyncSchedule implements ScheduleProviderInterface
{
    private ?Schedule $schedule = null;

    public function getSchedule(): Schedule
    {
        return $this->schedule ??= new Schedule()->add(
            RecurringMessage::cron('* * * * *', new RunMaintenanceCommand('integration:sync:dispatch-due')),
        );
    }
}
