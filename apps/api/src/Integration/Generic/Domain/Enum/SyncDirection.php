<?php

declare(strict_types=1);

namespace App\Integration\Generic\Domain\Enum;

/**
 * The overall direction of a {@see \App\Integration\Generic\Domain\Entity\SyncBinding}
 * (ADR-0022, epic APIC, ticket APIC-P3-01).
 *
 * Distinct from {@see MappingDirection} (per-field, uses `both`): a binding's
 * `bidirectional` runs both a read and a write leg, with the conflict policy
 * deciding the winner (APIC-P3-08).
 */
enum SyncDirection: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';
    case Bidirectional = 'bidirectional';

    public function readsRemote(): bool
    {
        return self::Inbound === $this || self::Bidirectional === $this;
    }

    public function writesRemote(): bool
    {
        return self::Outbound === $this || self::Bidirectional === $this;
    }
}
