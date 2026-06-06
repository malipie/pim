<?php

declare(strict_types=1);

namespace App\Catalog\Contracts\Event;

use App\Shared\Application\TenantAwareMessage;
use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Emitted when an object's primary master category is (re)assigned —
 * i.e. whenever `ObjectCategoryRepository::replaceForProduct()` commits a
 * non-null primary (CHC-07, #1290).
 *
 * The Channel context consumes this off-thread to auto-create channel
 * placements from the node mappings of that master category
 * ({@see \App\Channel\Application\Subscriber\AssignChannelPlacementsOnPrimaryCategoryAssigned}):
 * the operator picks one master category and the product lands on every
 * channel that mapped it — without touching each channel by hand.
 *
 * Carries `tenantId` so the async worker can rebind the tenant context
 * ({@see TenantAwareMessage}); without it the consumer would run with no
 * tenant filter and read across tenants.
 */
final readonly class ObjectPrimaryCategoryAssigned implements DomainEvent, TenantAwareMessage
{
    public function __construct(
        public Uuid $objectId,
        public Uuid $tenantId,
        public Uuid $primaryCategoryId,
        public DateTimeImmutable $occurredOn = new DateTimeImmutable(),
    ) {
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'catalog.object.primary-category-assigned';
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
