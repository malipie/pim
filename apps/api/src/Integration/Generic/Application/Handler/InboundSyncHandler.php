<?php

declare(strict_types=1);

namespace App\Integration\Generic\Application\Handler;

use App\Integration\Generic\Application\Sync\InboundSyncRunner;
use App\Integration\Generic\Domain\Entity\SyncBinding;
use App\Integration\Generic\Domain\Message\InboundSyncMessage;
use App\Integration\Generic\Domain\Repository\SyncBindingRepositoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Runs an inbound sync when an {@see InboundSyncMessage} arrives (APIC-P3-04).
 *
 * The tenant context + RLS GUC are restored by the shared rebinding /
 * GUC middleware before this runs (the message is {@see \App\Shared\Application\TenantAwareMessage}),
 * so the binding loads tenant-scoped. A binding deleted between dispatch and
 * delivery is a no-op. The chunked write + per-batch flush lives in the runner;
 * this handler is a thin entry point, so the flush-in-loop hygiene rule does
 * not apply here.
 */
#[AsMessageHandler]
final readonly class InboundSyncHandler
{
    public function __construct(
        private SyncBindingRepositoryInterface $bindings,
        private InboundSyncRunner $runner,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function __invoke(InboundSyncMessage $message): void
    {
        $binding = $this->bindings->findById($message->bindingId);
        if (!$binding instanceof SyncBinding) {
            $this->logger->info('Inbound sync skipped — binding no longer exists.', [
                'binding' => $message->bindingId->toRfc4122(),
            ]);

            return;
        }

        $this->runner->run($binding);
    }
}
