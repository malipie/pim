<?php

declare(strict_types=1);

namespace App\Asset\Contracts\Event;

use App\Shared\Application\TenantAwareMessage;
use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Dispatched on the `assets-thumbnails` async transport right after a
 * successful upload. The handler reads the original from Flysystem,
 * generates `thumb` (200×200) and `medium` (800×800) variants via
 * Imagick (with Ghostscript for PDFs), persists matching
 * {@see \App\Asset\Domain\Entity\AssetVariant} rows and flips the
 * parent Asset's `thumbnailsStatus` to `ready`.
 */
final readonly class AssetThumbnailsRequested implements DomainEvent, TenantAwareMessage
{
    public function __construct(
        public Uuid $assetId,
        public Uuid $tenantId,
        public string $storagePath,
        public string $mimeType,
        public DateTimeImmutable $occurredOn = new DateTimeImmutable(),
    ) {
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'asset.thumbnails.requested';
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
