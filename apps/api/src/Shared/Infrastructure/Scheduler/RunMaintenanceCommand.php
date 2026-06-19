<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Scheduler;

/**
 * AUD-051 (W2-11) — message that asks the worker to run one maintenance console
 * command on its scheduled cadence.
 *
 * Symfony Scheduler has no built-in "run a console command" recurring message,
 * so {@see MaintenanceSchedule} emits this and {@see RunMaintenanceCommandHandler}
 * dispatches it to the Symfony console application. Keeping the command name +
 * args in the payload (rather than one message class per command) keeps the
 * schedule declarative and the handler trivially testable.
 */
final readonly class RunMaintenanceCommand
{
    /**
     * @param array<string, scalar|bool> $arguments console input (options/args), e.g. `['--dry-run' => true]`
     */
    public function __construct(
        public string $command,
        public array $arguments = [],
    ) {
    }
}
