<?php

declare(strict_types=1);

namespace App\Integration\Generic\Domain\Enum;

/**
 * How a bidirectional {@see \App\Integration\Generic\Domain\Entity\SyncBinding}
 * resolves a value that changed on both sides since the last sync (ADR-0022,
 * epic APIC, ticket APIC-P3-01; resolver lands in APIC-P3-08).
 *
 * `lww` keeps the most recently updated value, `pim_wins`/`remote_wins` pin a
 * fixed side.
 */
enum ConflictPolicy: string
{
    case Lww = 'lww';
    case PimWins = 'pim_wins';
    case RemoteWins = 'remote_wins';
}
