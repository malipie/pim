<?php

declare(strict_types=1);

namespace App\Integration\Generic\Domain\Enum;

/**
 * The sync direction a {@see \App\Integration\Generic\Domain\Entity\FieldMapping}
 * applies in (ADR-0022, epic APIC, ticket APIC-P2-07).
 *
 * `inbound` pulls the remote field into the PIM target, `outbound` pushes the
 * PIM value to the remote, `both` does both (the conflict policy then decides
 * the winner — APIC-P3-08).
 */
enum MappingDirection: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';
    case Both = 'both';

    /** Whether this mapping participates in an inbound (remote → PIM) sync. */
    public function appliesInbound(): bool
    {
        return self::Inbound === $this || self::Both === $this;
    }

    /** Whether this mapping participates in an outbound (PIM → remote) sync. */
    public function appliesOutbound(): bool
    {
        return self::Outbound === $this || self::Both === $this;
    }
}
