<?php

declare(strict_types=1);

namespace App\Asset\Contracts\Event;

use App\Shared\Application\TenantAwareMessage;
use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Emitted by the async worker once both derivative variants are stored
 * and the asset's `thumbnailsStatus` flips to `ready`. The frontend
 * grid stops polling once it observes the new status.
 */
final readonly class AssetThumbnailsReady implements DomainEvent, TenantAwareMessage
{
    public function __construct(
        public Uuid $assetId,
        public Uuid $tenantId,
        public ?int $width,
        public ?int $height,
        public ?int $pageCount,
        public DateTimeImmutable $occurredOn = new DateTimeImmutable(),
    ) {
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'asset.thumbnails.ready';
    }

    public function aggregateId(): string
    {
        return $this->assetId->toRfc4122();
    }

    public function tenantId(): Uuid
    {
        return $this->tenantId;
    }
}
