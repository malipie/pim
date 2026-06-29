<?php

declare(strict_types=1);

namespace App\Integration\Generic\Domain\Message;

use App\Shared\Application\TenantAwareMessage;
use Symfony\Component\Uid\Uuid;

/**
 * Async trigger for one inbound sync of a {@see \App\Integration\Generic\Domain\Entity\SyncBinding}
 * (APIC-P3-04). Routed to the existing `import` transport (reusing the import
 * worker, per the epic's transport decision). Implements {@see TenantAwareMessage}
 * so the rebinding + RLS-GUC middleware restore the tenant context on the worker
 * before the handler loads the (tenant-scoped) binding — the routing wiring is
 * finalised in APIC-P3-05.
 */
final readonly class InboundSyncMessage implements TenantAwareMessage
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
