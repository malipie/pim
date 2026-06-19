<?php

declare(strict_types=1);

namespace App\Import\Infrastructure\Messenger;

use App\Import\Domain\Entity\ImportLog;
use App\Import\Domain\Entity\ImportSession;
use App\Import\Domain\Enum\ImportLogLevel;
use App\Import\Domain\Enum\ImportSessionStatus;
use App\Import\Domain\Message\ImageDownloadMessage;
use App\Import\Domain\Repository\ImportSessionRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Repository\TenantRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

/**
 * AUD-034 (W2-10) — dead-letter guard for media (image-download) batches.
 *
 * Import finalisation gates on {@see ImportSession::$pendingImageBatches}
 * reaching zero: {@see \App\Import\Application\Handler\ImageDownloadHandler}
 * decrements the counter (atomically) at the END of each batch and the last
 * batch finalises the session. When a batch throws BEFORE that decrement, the
 * retry policy exhausts (5×) and the message dead-letters — the counter is
 * never decremented, so the session sits `running` forever even though every
 * other batch finished and the row phase is done.
 *
 * This listener replays the SAME atomic decrement on the FINAL failure
 * (`willRetry() === false`) and, when it drives the counter to zero after the
 * row phase, finalises the session exactly as the handler does on success. The
 * lost images are surfaced: every image reference the batch carried bumps
 * `images_failed`, an error row is logged, and `error_count` is incremented so
 * the run lands as `partial` (not a silent `success`) — the operator sees that
 * media was dropped and can re-run.
 *
 * Retriable failures are ignored (the long-backoff retry policy still owns the
 * message); a terminal session is never clobbered.
 */
#[AsEventListener(event: WorkerMessageFailedEvent::class)]
final readonly class ImageDownloadDeadLetterListener
{
    public function __construct(
        private ImportSessionRepositoryInterface $sessions,
        private TenantRepositoryInterface $tenants,
        private TenantContext $tenantContext,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }

        $message = $event->getEnvelope()->getMessage();
        if (!$message instanceof ImageDownloadMessage) {
            return;
        }

        // Rebind the tenant (the failure unwound the messenger middleware that
        // normally sets it) so the session lookup + writes are scoped correctly.
        $tenant = $this->tenants->findById($message->tenantId);
        if (null === $tenant) {
            return;
        }
        $this->tenantContext->set($tenant);

        $lostImages = $this->countImageRefs($message);

        // Mirror the handler's success path: ONE atomic statement bumps the lost
        // counters and closes this batch's slot in the gate. The status guard
        // makes it a no-op against a session a concurrent handler / the run
        // dead-letter listener already finalised, so a terminal outcome is never
        // clobbered. RETURNING reports the post-decrement gate state.
        //
        // tenant-safe: per-row UPDATE keyed by primary key (id from the
        // tenant-scoped ImageDownloadMessage); tenant already rebound above.
        $row = $this->entityManager->getConnection()->fetchAssociative(
            'UPDATE import_sessions'
            .' SET images_failed = images_failed + :lost,'
            .'     error_count = error_count + 1,'
            .'     pending_image_batches = GREATEST(0, pending_image_batches - 1)'
            .' WHERE id = :id'
            ."   AND status IN ('pending', 'running', 'paused')"
            .' RETURNING pending_image_batches, row_phase_complete, status',
            [
                'lost' => $lostImages,
                'id' => $message->importSessionId->toRfc4122(),
            ],
        );
        if (!\is_array($row)) {
            return;
        }

        $pending = \is_scalar($row['pending_image_batches']) ? (int) $row['pending_image_batches'] : 1;
        $rowPhaseDone = (bool) $row['row_phase_complete'];

        $this->logger->warning('Import media batch dead-lettered; images were lost.', [
            'import_session_id' => $message->importSessionId->toRfc4122(),
            'lost_images' => $lostImages,
            'pending_image_batches' => $pending,
        ]);

        // The batch that drives the gate to zero AFTER the row phase finalises
        // the run — same condition as the handler's success path.
        if (0 !== $pending || !$rowPhaseDone) {
            return;
        }

        // Re-fetch a MANAGED tenant on the cleared EM so the assignment listener
        // stamps the new ImportLog with a managed entity (mirrors the handler's
        // reattachTenant on its post-ingest EM clear).
        $this->entityManager->clear();
        $managedTenant = $this->tenants->findById($message->tenantId);
        if (null === $managedTenant) {
            return;
        }
        $this->tenantContext->set($managedTenant);
        $session = $this->sessions->findById($message->importSessionId);
        // markCompleted() only transitions from Running; a Paused session keeps
        // its (now drained) gate and finalises when the operator resumes.
        if (!$session instanceof ImportSession || ImportSessionStatus::Running !== $session->getStatus()) {
            return;
        }

        $this->entityManager->persist(new ImportLog(
            importSession: $session,
            rowNumber: 0,
            level: ImportLogLevel::Error,
            message: \sprintf(
                'Pobieranie %d obrazów nie powiodło się po wyczerpaniu prób (partia trafiła do kolejki błędów). Import zakończono częściowo.',
                $lostImages,
            ),
        ));
        $session->markCompleted();
        $this->sessions->save($session);
    }

    private function countImageRefs(ImageDownloadMessage $message): int
    {
        $count = 0;
        foreach ($message->jobs as $job) {
            $count += \count($job->urls) + \count($job->zipNames);
        }

        return $count;
    }
}
