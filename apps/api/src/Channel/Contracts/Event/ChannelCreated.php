<?php

declare(strict_types=1);

namespace App\Channel\Contracts\Event;

use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Emitted when {@see \App\Channel\Domain\Entity\Channel} is first
 * persisted. Integration adapters subscribe to provision per-channel
 * state (Shopify location, BaseLinker connection scope, …) without
 * having to listen on Doctrine's lifecycle events.
 */
final readonly class ChannelCreated implements DomainEvent
{
    public function __construct(
        public Uuid $channelId,
        public Uuid $tenantId,
        public string $code,
        public DateTimeImmutable $occurredOn = new DateTimeImmutable(),
    ) {
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'channel.created';
    }

    public function aggregateId(): string
    {
        return $this->channelId->toRfc4122();
    }
}
