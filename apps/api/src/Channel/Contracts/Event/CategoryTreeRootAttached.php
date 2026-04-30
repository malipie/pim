<?php

declare(strict_types=1);

namespace App\Channel\Contracts\Event;

use App\Shared\Application\TenantAwareMessage;
use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Emitted when a {@see \App\Channel\Domain\Entity\Channel} is wired to
 * a category tree root (or detached from one). Channel exporters use
 * this signal to (re)scope which categories they will publish.
 */
final readonly class CategoryTreeRootAttached implements DomainEvent, TenantAwareMessage
{
    public function __construct(
        public Uuid $channelId,
        public Uuid $tenantId,
        public ?Uuid $categoryTreeRootId,
        public DateTimeImmutable $occurredOn = new DateTimeImmutable(),
    ) {
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'channel.category-tree-root-attached';
    }

    public function aggregateId(): string
    {
        return $this->channelId->toRfc4122();
    }

    public function tenantId(): Uuid
    {
        return $this->tenantId;
    }
}
