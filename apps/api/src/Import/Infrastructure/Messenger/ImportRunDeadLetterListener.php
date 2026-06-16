<?php

declare(strict_types=1);

namespace App\Import\Infrastructure\Messenger;

use App\Import\Domain\Entity\ImportSession;
use App\Import\Domain\Enum\ImportSessionStatus;
use App\Import\Domain\Message\ImportRunMessage;
use App\Import\Domain\Repository\ImportSessionRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Repository\TenantRepositoryInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

/**
 * IMP2-2.9 (#1485) — dead-letter guard for import runs. When an
 * {@see ImportRunMessage} exhausts its retries (e.g. it never got the
 * per-tenant bulk lock because a long import / bulk op held it), the message
 * lands in the `failed` transport and the {@see ImportSession} would otherwise
 * sit in `pending`/`running` forever. This flips it to `failed` with a readable
 * message so the operator knows to re-run, rather than staring at a stuck row.
 *
 * Fires only on the FINAL failure (`willRetry() === false`); retriable failures
 * are left alone so the long-backoff retry policy can do its job.
 */
#[AsEventListener(event: WorkerMessageFailedEvent::class)]
final readonly class ImportRunDeadLetterListener
{
    public function __construct(
        private ImportSessionRepositoryInterface $sessions,
        private TenantRepositoryInterface $tenants,
        private TenantContext $tenantContext,
    ) {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }

        $message = $event->getEnvelope()->getMessage();
        if (!$message instanceof ImportRunMessage) {
            return;
        }

        // Rebind the tenant (the failure unwound the messenger middleware that
        // normally sets it) so the session lookup is scoped correctly.
        $tenant = $this->tenants->findById($message->tenantId);
        if (null === $tenant) {
            return;
        }
        $this->tenantContext->set($tenant);

        $session = $this->sessions->findById($message->importSessionId);
        if (!$session instanceof ImportSession) {
            return;
        }
        // Only a still-active session may be failed; a terminal status the handler
        // already recorded (paused/cancelled/completed) must not be clobbered.
        if (!\in_array($session->getStatus(), [
            ImportSessionStatus::Pending,
            ImportSessionStatus::Running,
            ImportSessionStatus::Paused,
        ], true)) {
            return;
        }

        $session->markFailed(
            'Nie udało się uzyskać blokady operacji masowych — inna operacja trwała '
            .'zbyt długo. Uruchom import ponownie.',
        );
        $this->sessions->save($session);
    }
}
