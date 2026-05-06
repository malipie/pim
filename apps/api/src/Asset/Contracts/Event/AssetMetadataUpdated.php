<?php

declare(strict_types=1);

namespace App\Asset\Contracts\Event;

use App\Shared\Application\TenantAwareMessage;
use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Emitted on PATCH /api/assets/{id}. The payload only carries the
 * fields the caller actually changed; downstream subscribers (search
 * indexer, audit log) read the entity for the full snapshot.
 */
final readonly class AssetMetadataUpdated implements DomainEvent, TenantAwareMessage
{
    /**
     * @param array<string, string> $alt  localised alt text payload (locale → value), null if unchanged
     * @param array<int, string>    $tags updated tag list, null if unchanged
     */
    public function __construct(
        public Uuid $assetId,
        public Uuid $tenantId,
        public ?string $code = null,
        public ?array $alt = null,
        public ?array $tags = null,
        public DateTimeImmutable $occurredOn = new DateTimeImmutable(),
    ) {
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'asset.metadata.updated';
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
