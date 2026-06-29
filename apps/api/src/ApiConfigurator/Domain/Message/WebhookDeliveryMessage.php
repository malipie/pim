<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Domain\Message;

use App\Shared\Application\TenantAwareMessage;
use Symfony\Component\Uid\Uuid;

/**
 * Async trigger to (re)attempt one webhook delivery (APIC-P4-05). Carries only
 * the {@see \App\ApiConfigurator\Domain\Entity\WebhookDelivery} id; the handler
 * loads the stored payload + the profile's secret. {@see TenantAwareMessage} so
 * the rebinding + RLS-GUC middleware restore tenant context on the worker.
 *
 * Routed to a transport with an exponential retry strategy; exhausted retries
 * dead-letter to the `failed` transport.
 */
final readonly class WebhookDeliveryMessage implements TenantAwareMessage
{
    public function __construct(
        public Uuid $deliveryId,
        public Uuid $tenant,
    ) {
    }

    public function tenantId(): Uuid
    {
        return $this->tenant;
    }
}
