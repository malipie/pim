<?php

declare(strict_types=1);

namespace App\Catalog\Contracts\Event;

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
final readonly class ObjectAttributesChanged implements DomainEvent
{
    /**
     * @param list<string> $changedAttributeCodes attribute codes whose values were affected
     */
    public function __construct(
        public Uuid $objectId,
        public Uuid $tenantId,
        public array $changedAttributeCodes = [],
        public DateTimeImmutable $occurredOn = new DateTimeImmutable(),
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
}
