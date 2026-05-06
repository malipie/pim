<?php

declare(strict_types=1);

namespace App\Backup\Application\Handler;

use App\Backup\Application\Service\BackupRunnerInterface;
use App\Backup\Domain\Entity\Backup;
use App\Backup\Domain\Message\BackupSnapshotMessage;
use App\Backup\Domain\Repository\BackupRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use LogicException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

/**
 * IMP-06 (#447) — drives the pgBackRest snapshot lifecycle.
 *
 * State machine: pending → running → completed / failed. The runner
 * is injected behind {@see BackupRunnerInterface} so tests can stub
 * the CLI invocation without ever spawning a process.
 */
#[AsMessageHandler]
final readonly class BackupSnapshotHandler
{
    private LoggerInterface $logger;

    public function __construct(
        private BackupRepositoryInterface $backups,
        private BackupRunnerInterface $runner,
        private TenantContext $tenantContext,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function __invoke(BackupSnapshotMessage $message): void
    {
        $backup = $this->backups->findById($message->backupId);
        if (!$backup instanceof Backup) {
            $this->logger->warning('Backup snapshot {id} not found, skipping.', [
                'id' => $message->backupId->toRfc4122(),
            ]);

            return;
        }

        $tenant = $backup->getTenant();
        if (!$tenant instanceof Tenant) {
            $backup->markFailed('Backup row has no tenant assignment.');
            $this->backups->save($backup);

            return;
        }

        $this->tenantContext->set($tenant);

        try {
            $backup->markRunning();
            $this->backups->save($backup);
        } catch (LogicException) {
            // Already running / terminal — bail without state churn.
            return;
        }

        try {
            $result = $this->runner->run();
        } catch (Throwable $exception) {
            $current = $this->backups->findById($backup->getId());
            if ($current instanceof Backup) {
                $current->markFailed($exception->getMessage());
                $this->backups->save($current);
            }
            throw $exception;
        }

        $current = $this->backups->findById($backup->getId());
        if (!$current instanceof Backup) {
            return;
        }

        if ($result->success) {
            $current->markCompleted($result->sizeBytes, $result->pgbackrestLabel);
        } else {
            $current->markFailed($result->errorMessage ?? 'pgBackRest failed without details.');
        }
        $this->backups->save($current);
    }
}
