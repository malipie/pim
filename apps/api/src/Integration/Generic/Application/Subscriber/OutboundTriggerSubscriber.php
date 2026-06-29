<?php

declare(strict_types=1);

namespace App\Integration\Generic\Application\Subscriber;

use App\Catalog\Contracts\BulkGuard;
use App\Catalog\Contracts\Event\ObjectAttributesChanged;
use App\Integration\Generic\Domain\Message\OutboundSyncMessage;
use App\Integration\Generic\Domain\Repository\SyncBindingRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Enqueues an outbound sync when a catalog object changes (APIC-P3-07).
 *
 * Listens to the cross-BC {@see ObjectAttributesChanged} domain event and, for
 * every enabled binding that writes to a remote (outbound/bidirectional) AND
 * targets the changed object's ObjectType, dispatches an {@see OutboundSyncMessage}.
 *
 * Bulk flows (import, bulk edit) run under {@see BulkGuard} and are skipped — a
 * 50k import must not enqueue a sync per row; those paths trigger one run when
 * they finish. The event without an ObjectType id (legacy emitter) is ignored.
 */
#[AsMessageHandler]
final readonly class OutboundTriggerSubscriber
{
    public function __construct(
        private BulkGuard $bulkGuard,
        private SyncBindingRepositoryInterface $bindings,
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(ObjectAttributesChanged $event): void
    {
        if ($this->bulkGuard->isBulk() || null === $event->objectTypeId) {
            return;
        }

        $objectTypeId = $event->objectTypeId->toRfc4122();

        foreach ($this->bindings->findEnabled() as $binding) {
            if (!$binding->getDirection()->writesRemote()) {
                continue;
            }
            if ($binding->getObjectTypeId()->toRfc4122() !== $objectTypeId) {
                continue;
            }

            $this->bus->dispatch(new OutboundSyncMessage($binding->getId(), $event->tenantId));
        }
    }
}
