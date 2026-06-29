<?php

declare(strict_types=1);

namespace App\Integration\Generic\Domain\Enum;

/**
 * Which side wins a bidirectional value conflict (ADR-0022, epic APIC, ticket
 * APIC-P3-08) — the PIM value is kept, or the remote value is applied.
 */
enum ConflictWinner: string
{
    case Pim = 'pim';
    case Remote = 'remote';
}
