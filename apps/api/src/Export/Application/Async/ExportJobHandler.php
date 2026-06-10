<?php

declare(strict_types=1);

namespace App\Export\Application\Async;

use App\Export\Application\Sync\SyncExportRunner;
use App\Export\Domain\Entity\ExportSession;
use App\Export\Domain\Enum\ExportStatus;
use App\Export\Domain\Message\RunExportMessage;
use App\Export\Domain\Repository\ExportSessionRepositoryInterface;
use App\Shared\Application\AbstractBatchHandler;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

/**
 * EXP-06 (#585) — Async ExportJobHandler.
 *
 * Driven by Symfony Messenger when the sync controller (EXP-05) decides
 * target_count crosses the sync threshold (PRD §11.4). The flow:
 *
 *   1. Load the persisted ExportSession (state survives retries).
 *   2. Set tenant context so Doctrine TenantFilter scopes correctly
 *      inside the worker process.
 *   3. Mark running + publish Mercure status event (EXP-13 grid wakes
 *      up its "running" row).
 *   4. Run the export to a local temp file via the same SyncExportRunner
 *      the synchronous controller uses — identical row/chunk logic.
 *   5. Upload the temp file to the tenant-scoped MinIO prefix:
 *      `<tenant_id>/<session_id>.<format>`.
 *   6. Mark done (markDone() inside the runner) + publish status event.
 *
 * Memory safety: the runner already iterates via {@see \App\Export\Application\Builder\ExportBuilder}
 * which uses Doctrine `findByObject` row-by-row. We funnel through
 * {@see flushAndClear()} after every chunk so the worker stays under
 * its 50 MB budget (CLAUDE.md §3.10).
 *
 * Failure modes:
 *   - target_scope=filter — RuntimeException from the runner. Marked as
 *     `error` with the exception message; not retried (configuration
 *     bug, not transient).
 *   - MinIO upload failure — FilesystemException. Marked as `error` so
 *     the user can rerun; the temp file is unconditionally removed.
 *   - Any other throwable — same treatment: mark error, publish status,
 *     re-throw so Messenger can dead-letter.
 */
#[AsMessageHandler]
final class ExportJobHandler extends AbstractBatchHandler
{
    private readonly LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        private readonly SyncExportRunner $runner,
        private readonly ExportSessionRepositoryInterface $sessions,
        private readonly FilesystemOperator $exportsStorage,
        private readonly ExportProgressPublisher $progress,
        private readonly TenantContext $tenantContext,
        private readonly Connection $connection,
        ?LoggerInterface $logger = null,
        int $batchSize = 1000,
    ) {
        parent::__construct($entityManager, $batchSize);
        $this->logger = $logger ?? new NullLogger();
    }

    public function __invoke(RunExportMessage $message): void
    {
        $session = $this->sessions->findById($message->exportSessionId);
        if (!$session instanceof ExportSession) {
            return;
        }

        // Idempotency guard — Messenger sync transport (dev) + retry policies
        // can dispatch the same envelope twice on the same in-flight session.
        // Without the guard the second pass calls markRunning() on a 'done'
        // session and throws "Cannot transition from done", which then
        // chains into markError() on the same 'done' session for another
        // throw. Skip if not in pending state.
        if (ExportStatus::Pending !== $session->getStatus()) {
            $this->logger->info('Export job handler skipped — session not pending', [
                'session_id' => $session->getId()->toRfc4122(),
                'status' => $session->getStatus()->value,
            ]);

            return;
        }

        $tenant = $session->getTenant();
        if (!$tenant instanceof Tenant) {
            $session->markError('Export session has no tenant assignment.');
            $this->sessions->save($session);

            return;
        }

        $this->tenantContext->set($tenant);

        $session->markRunning();
        $this->sessions->save($session);
        $this->progress->status($session);

        $tempPath = $this->prepareTempFile($session);
        try {
            // EXR-15 — live progress every chunk + graceful cancellation:
            // the cancel endpoint flips the PERSISTED status; we read it
            // straight from the DB (the in-memory entity is stale inside
            // the long-running loop).
            $startedAt = microtime(true);
            $onChunk = function (int $rowsDone) use ($session, $startedAt): void {
                $elapsed = microtime(true) - $startedAt;
                $rate = $elapsed > 0 ? $rowsDone / $elapsed : 0.0;
                $remaining = max(0, $session->getTargetCount() - $rowsDone);
                $eta = $rate > 0 ? (int) ceil($remaining / $rate) : null;
                $this->progress->progress($session, $rowsDone, $eta);

                $statusNow = $this->connection->fetchOne(
                    'SELECT status FROM export_sessions WHERE id = :id',
                    ['id' => $session->getId()->toRfc4122()],
                );
                if (ExportStatus::Cancelled->value === $statusNow) {
                    throw new ExportCancelledException('Export cancelled by the user.');
                }
            };

            $this->runner->runToFile($session, $tempPath, $onChunk);
            $this->uploadToMinio($session, $tenant, $tempPath);
            $this->progress->progress($session, $session->getSuccessCount(), estimatedSecondsRemaining: 0);
            $this->progress->status($session);
        } catch (ExportCancelledException) {
            // EXR-15 — the cancel endpoint already persisted the terminal
            // status; refresh the stale entity and notify subscribers so
            // the card drops out of "W toku" instantly.
            $this->entityManager->refresh($session);
            $this->progress->status($session);
            $this->logger->info('Export job cancelled by user', [
                'session_id' => $session->getId()->toRfc4122(),
            ]);
        } catch (Throwable $error) {
            // markError throws if the session has already transitioned to
            // 'done' (e.g. runToFile completed but MinIO upload failed
            // afterwards). In that case the rows are already in the temp
            // file, but the user cannot download them — we keep the
            // session as 'done' and just log the post-flight failure so
            // the operator sees the smell without a 500 to the FE.
            if (ExportStatus::Done === $session->getStatus()) {
                $this->logger->error('Export job post-flight failure on already-done session', [
                    'session_id' => $session->getId()->toRfc4122(),
                    'error' => $error->getMessage(),
                ]);
            } else {
                $session->markError($error->getMessage());
                $this->sessions->save($session);
                $this->progress->status($session);
                $this->logger->error('Export job failed', [
                    'session_id' => $session->getId()->toRfc4122(),
                    'error' => $error->getMessage(),
                ]);
            }
            // No re-throw — failure is captured in session state; the
            // operator restarts via the rerun endpoint (EXP-08). Re-throwing
            // would surface a 500 (or HandlerFailedException unwrap) at the
            // sync transport in dev, which the FE then renders as a red
            // alarm on the modal even though the export itself succeeded.
        } finally {
            @unlink($tempPath);
        }
    }

    private function prepareTempFile(ExportSession $session): string
    {
        $ext = $session->getFormat()->value;
        $tmp = tempnam(sys_get_temp_dir(), 'pim-export-async-');
        if (false === $tmp) {
            throw new RuntimeException('Unable to allocate temp file for async export.');
        }
        $withExt = $tmp.'.'.$ext;

        return @rename($tmp, $withExt) ? $withExt : $tmp;
    }

    /**
     * Upload the freshly written export file to the tenant-scoped MinIO
     * prefix. Naming follows PRD §11.1: `<tenant_id>/<session_id>.<format>`.
     */
    private function uploadToMinio(ExportSession $session, Tenant $tenant, string $localPath): void
    {
        $remotePath = sprintf(
            '%s/%s.%s',
            $tenant->getId()->toRfc4122(),
            $session->getId()->toRfc4122(),
            $session->getFormat()->value,
        );

        $stream = @fopen($localPath, 'r');
        if (false === $stream) {
            throw new RuntimeException(sprintf('Unable to read local export file "%s" for MinIO upload.', $localPath));
        }
        try {
            $this->exportsStorage->writeStream($remotePath, $stream);
        } catch (FilesystemException $error) {
            throw new RuntimeException(
                sprintf('MinIO upload failed for export %s: %s', $session->getId()->toRfc4122(), $error->getMessage()),
                previous: $error,
            );
        } finally {
            if (\is_resource($stream)) {
                fclose($stream);
            }
        }

        // Replace local path with MinIO key in the session so EXP-08
        // download endpoint can mint a presigned URL.
        $session->setFilePath($remotePath);
        $this->sessions->save($session);
    }
}
