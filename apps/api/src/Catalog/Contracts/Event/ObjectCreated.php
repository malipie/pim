<?php

declare(strict_types=1);

namespace App\Catalog\Contracts\Event;

use App\Catalog\Domain\ObjectKind;
use App\Shared\Application\TenantAwareMessage;
use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Emitted by {@see \App\Catalog\Domain\Entity\CatalogObject} once a new
 * row is persisted. Cross-BC subscribers (search indexer in Faza 2,
 * channel publishers, asset linker) react to creation via this event.
 *
 * Implements {@see TenantAwareMessage} so the messenger middleware
 * (HIGH-002 / 2026-04-29) can rebind the tenant context when this
 * event lands on an async transport.
 */
final readonly class ObjectCreated implements DomainEvent, TenantAwareMessage
{
    public function __construct(
        public Uuid $objectId,
        public ObjectKind $kind,
        public string $code,
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
        return 'catalog.object.created';
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
