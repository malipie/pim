<?php

declare(strict_types=1);

namespace App\Identity\Contracts\Event;

use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Emitted on a successful refresh-token rotation in
 * {@see \App\Identity\Application\RefreshTokenService::rotate()}. Audit
 * logs and the agent layer attribute side-effects to the freshly
 * issued token's family.
 */
final readonly class RefreshTokenRotated implements DomainEvent
{
    public function __construct(
        public Uuid $tokenId,
        public Uuid $userId,
        public Uuid $tenantId,
        public Uuid $familyId,
        public DateTimeImmutable $occurredOn = new DateTimeImmutable(),
    ) {
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'identity.refresh-token-rotated';
    }

    public function aggregateId(): string
    {
        return $this->tokenId->toRfc4122();
    }
}
