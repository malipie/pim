<?php

declare(strict_types=1);

namespace App\Asset\Contracts\Event;

use App\Shared\Application\TenantAwareMessage;
use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Emitted when a derivative {@see \App\Asset\Domain\Entity\AssetVariant}
 * is created — e.g. a generated thumbnail, webp transcode, or alternate
 * crop. Tenant scope is inherited from the parent Asset, carried here
 * to keep subscriber routing simple.
 */
final readonly class AssetVariantCreated implements DomainEvent, TenantAwareMessage
{
    public function __construct(
        public Uuid $variantId,
        public Uuid $assetId,
        public Uuid $tenantId,
        public string $variantCode,
        public DateTimeImmutable $occurredOn = new DateTimeImmutable(),
    ) {
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'asset.variant-created';
    }

    public function aggregateId(): string
    {
        return $this->variantId->toRfc4122();
    }

    public function tenantId(): Uuid
    {
        return $this->tenantId;
    }
}
