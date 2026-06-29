<?php

declare(strict_types=1);

namespace App\Integration\Generic\Application\Handler;

use App\Integration\Generic\Application\Sync\OutboundSyncRunner;
use App\Integration\Generic\Domain\Entity\SyncBinding;
use App\Integration\Generic\Domain\Message\OutboundSyncMessage;
use App\Integration\Generic\Domain\Repository\SyncBindingRepositoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Runs an outbound sync when an {@see OutboundSyncMessage} arrives (APIC-P3-06).
 *
 * Tenant context + RLS GUC are restored by the shared worker middleware before
 * this runs (the message is {@see \App\Shared\Application\TenantAwareMessage}),
 * so the binding loads tenant-scoped. A binding deleted between dispatch and
 * delivery is a no-op. The push loop + per-record flush live in the runner.
 */
#[AsMessageHandler]
final readonly class OutboundSyncHandler
{
    public function __construct(
        private SyncBindingRepositoryInterface $bindings,
        private OutboundSyncRunner $runner,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function __invoke(OutboundSyncMessage $message): void
    {
        $binding = $this->bindings->findById($message->bindingId);
        if (!$binding instanceof SyncBinding) {
            $this->logger->info('Outbound sync skipped — binding no longer exists.', [
                'binding' => $message->bindingId->toRfc4122(),
            ]);

            return;
        }

        $this->runner->run($binding);
    }
}
