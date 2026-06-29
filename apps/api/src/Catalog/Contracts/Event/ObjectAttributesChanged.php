<?php

declare(strict_types=1);

namespace App\Catalog\Contracts\Event;

use App\Shared\Application\TenantAwareMessage;
use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Emitted whenever the denormalized `attributes_indexed` cache on a
 * CatalogObject changes — i.e. when ObjectValues are written and the
 * sync listener (or the async rebuilder) has pushed the new shape onto
 * the parent row.
 *
 * Search indexers consume this to reindex; channel publishers compare
 * against last published payload.
 */
final readonly class ObjectAttributesChanged implements DomainEvent, TenantAwareMessage
{
    /**
     * @param list<string> $changedAttributeCodes attribute codes whose values were affected
     */
    public function __construct(
        public Uuid $objectId,
        public Uuid $tenantId,
        public array $changedAttributeCodes = [],
        public DateTimeImmutable $occurredOn = new DateTimeImmutable(),
        // APIC-P3-07 — lets cross-BC consumers (the outbound-sync trigger) route
        // by object type without a second lookup. Null only for legacy emitters.
        public ?Uuid $objectTypeId = null,
    ) {
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'catalog.object.attributes-changed';
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
