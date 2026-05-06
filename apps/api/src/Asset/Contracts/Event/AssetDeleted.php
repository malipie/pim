<?php

declare(strict_types=1);

namespace App\Asset\Contracts\Event;

use App\Shared\Application\TenantAwareMessage;
use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Emitted right before a row is removed from the assets table; carries
 * the storage paths so subscribers (the storage cleanup handler) can
 * unlink original + variants from the bucket without a second DB lookup.
 *
 * @param array<int, string> $variantStoragePaths
 */
final readonly class AssetDeleted implements DomainEvent, TenantAwareMessage
{
    /**
     * @param array<int, string> $variantStoragePaths
     */
    public function __construct(
        public Uuid $assetId,
        public Uuid $tenantId,
        public string $originalStoragePath,
        public array $variantStoragePaths,
        public DateTimeImmutable $occurredOn = new DateTimeImmutable(),
    ) {
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'asset.deleted';
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
