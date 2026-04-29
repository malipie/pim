<?php

declare(strict_types=1);

namespace App\Catalog\Contracts\Event;

use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Emitted when {@see \App\Catalog\Domain\Entity\CatalogObject} transitions
 * to status=published. Triggers downstream publication (search index sync,
 * channel push, etc.).
 */
final readonly class ObjectPublished implements DomainEvent
{
    public function __construct(
        public Uuid $objectId,
        public Uuid $tenantId,
        public DateTimeImmutable $occurredOn = new DateTimeImmutable(),
    ) {
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'catalog.object.published';
    }

    public function aggregateId(): string
    {
        return $this->objectId->toRfc4122();
    }
}
