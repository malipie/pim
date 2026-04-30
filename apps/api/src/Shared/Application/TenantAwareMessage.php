<?php

declare(strict_types=1);

namespace App\Shared\Application;

use Symfony\Component\Uid\Uuid;

/**
 * Marker for messages that carry their originating tenant id directly
 * on the payload — e.g. domain events with a `tenantId` field.
 *
 * The {@see \App\Shared\Infrastructure\Messenger\TenantContextRebindingMiddleware}
 * uses this as a fallback when the envelope has no
 * {@see \App\Shared\Infrastructure\Messenger\Stamp\TenantStamp}.
 *
 * NOTE: This interface deliberately does not extend
 * {@see \App\Shared\Domain\DomainEvent} — application messages
 * (commands, sync jobs) can opt in too without dragging the domain
 * event surface along.
 */
interface TenantAwareMessage
{
    public function tenantId(): Uuid;
}
