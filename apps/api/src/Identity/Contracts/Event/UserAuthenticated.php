<?php

declare(strict_types=1);

namespace App\Identity\Contracts\Event;

use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Emitted when {@see \App\Identity\Domain\Entity\User::recordLogin()} fires —
 * i.e. on a successful authentication round-trip. Audit logs subscribe to
 * this; the agent layer (Faza 2) attributes tool runs to the principal.
 */
final readonly class UserAuthenticated implements DomainEvent
{
    public function __construct(
        public Uuid $userId,
        public Uuid $tenantId,
        public string $email,
        public DateTimeImmutable $occurredOn = new DateTimeImmutable(),
    ) {
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'identity.user-authenticated';
    }

    public function aggregateId(): string
    {
        return $this->userId->toRfc4122();
    }
}
