<?php

declare(strict_types=1);

namespace App\Integration\Generic\Domain\Message;

use App\Shared\Application\TenantAwareMessage;
use Symfony\Component\Uid\Uuid;

/**
 * Async trigger for one outbound (PIM → remote) sync of a {@see \App\Integration\Generic\Domain\Entity\SyncBinding}
 * (APIC-P3-06). Routed to the existing `import` transport; {@see TenantAwareMessage}
 * so the rebinding + RLS-GUC middleware restore tenant context on the worker.
 */
final readonly class OutboundSyncMessage implements TenantAwareMessage
{
    public function __construct(
        public Uuid $bindingId,
        public Uuid $tenant,
    ) {
    }

    public function tenantId(): Uuid
    {
        return $this->tenant;
    }
}
