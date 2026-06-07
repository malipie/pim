<?php

declare(strict_types=1);

namespace App\Catalog\Contracts\Event;

use App\Shared\Application\TenantAwareMessage;
use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Emitted whenever an object's master-category set changes — assignment,
 * removal, primary change or full replace (CHC #1314, superseding the
 * primary-only event from CHC-07).
 *
 * The Channel context consumes this to reconcile the object's channel
 * placements: the product lands on every channel mapped by ANY of its
 * categories (primary takes precedence on conflicts)
 * ({@see \App\Channel\Application\Subscriber\ReconcileChannelPlacementsOnCategoriesChanged}).
 *
 * Carries `tenantId` so the async worker rebinds the tenant context
 * ({@see TenantAwareMessage}) before the tenant-filtered repositories run.
 */
final readonly class ObjectCategoriesChanged implements DomainEvent, TenantAwareMessage
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
        return 'catalog.object.categories-changed';
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
