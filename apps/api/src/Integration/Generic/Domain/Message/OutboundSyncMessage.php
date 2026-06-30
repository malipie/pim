<?php

declare(strict_types=1);

namespace App\Integration\Generic\Domain\Message;

use App\Shared\Application\TenantAwareMessage;
use Symfony\Component\Uid\Uuid;

/**
 * Async trigger for one outbound (PIM → remote) sync of a {@see \App\Integration\Generic\Domain\Entity\SyncBinding}
 * (APIC-P3-06). Routed to the existing `import` transport; {@see TenantAwareMessage}
 * so the rebinding + RLS-GUC middleware restore tenant context on the worker.
 *
 * `dryRun` (#1889) builds and logs the would-be payloads without calling the
 * remote — a safety preview before a real push that, unscoped, would flood the
 * whole catalog into the external shop.
 */
final readonly class OutboundSyncMessage implements TenantAwareMessage
{
    public function __construct(
        public Uuid $bindingId,
        public Uuid $tenant,
        public bool $dryRun = false,
    ) {
    }

    public function tenantId(): Uuid
    {
        return $this->tenant;
    }
}
