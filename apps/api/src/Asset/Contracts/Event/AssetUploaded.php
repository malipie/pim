<?php

declare(strict_types=1);

namespace App\Asset\Contracts\Event;

use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Emitted when {@see \App\Asset\Domain\Entity\Asset} finishes initial
 * persistence — i.e. the original file has landed in object storage and
 * the Doctrine row is in the database. Channel publishers and the search
 * indexer subscribe to this for `media` blocks; Catalog can pick it up
 * to denormalize media URLs into `attributes_indexed`.
 */
final readonly class AssetUploaded implements DomainEvent
{
    public function __construct(
        public Uuid $assetId,
        public Uuid $tenantId,
        public string $code,
        public string $mimeType,
        public ?Uuid $linkedObjectId = null,
        public DateTimeImmutable $occurredOn = new DateTimeImmutable(),
    ) {
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'asset.uploaded';
    }

    public function aggregateId(): string
    {
        return $this->assetId->toRfc4122();
    }
}
