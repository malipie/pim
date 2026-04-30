<?php

declare(strict_types=1);

namespace App\Catalog\Contracts\Event;

use App\Shared\Application\TenantAwareMessage;
use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Emitted when {@see \App\Catalog\Domain\Entity\CatalogObject} transitions
 * to status=archived. Channel publishers depublish; search indexers drop
 * the row from active indices.
 */
final readonly class ObjectArchived implements DomainEvent, TenantAwareMessage
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
        return 'catalog.object.archived';
    }

    public function aggregateId(): string
    {
        return $this->objectId->toRfc4122();
    }

    public function tenantId(): Uuid
    {
        return $this->tenantId;
    }
}
