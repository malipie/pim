<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Scheduler;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * AUD-051 (W2-11) — runs the scheduled maintenance console command on the
 * worker.
 *
 * The `scheduler_maintenance` transport is auto-registered by the scheduler from
 * {@see MaintenanceSchedule}'s `#[AsSchedule('maintenance')]` (no messenger.yaml
 * entry needed) and must be drained by `messenger:consume scheduler_maintenance`
 * (docker-compose `worker`), so this handler executes in the worker process. It
 * boots a console {@see Application} over the SAME kernel
 * (no sub-process), runs the requested command, and throws on a non-zero exit so
 * Messenger's retry / failure transport handles a failed sweep instead of
 * silently swallowing it.
 *
 * Memory (FrankenPHP worker mode, §3.10): each maintenance command does its own
 * `EntityManager::clear()` batching; the console Application adds no persistent
 * state beyond the request.
 */
#[AsMessageHandler]
final readonly class RunMaintenanceCommandHandler
{
    public function __construct(
        private KernelInterface $kernel,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RunMaintenanceCommand $message): void
    {
        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $input = new ArrayInput(['command' => $message->command] + $message->arguments);
        $input->setInteractive(false);
        $output = new BufferedOutput();

        $exitCode = $application->doRun($input, $output);

        $this->logger->info('Scheduled maintenance command finished.', [
            'command' => $message->command,
            'exit_code' => $exitCode,
            'output' => $output->fetch(),
        ]);

        if (0 !== $exitCode) {
            throw new RuntimeException(\sprintf(
                'Scheduled maintenance command "%s" exited with code %d.',
                $message->command,
                $exitCode,
            ));
        }
    }
}
