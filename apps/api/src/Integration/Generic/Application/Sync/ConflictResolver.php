<?php

declare(strict_types=1);

namespace App\Integration\Generic\Application\Sync;

use App\Integration\Generic\Domain\Enum\ConflictPolicy;
use App\Integration\Generic\Domain\Enum\ConflictWinner;
use DateTimeImmutable;

/**
 * Resolves a bidirectional value conflict and guards against sync loops
 * (ADR-0022, epic APIC, ticket APIC-P3-08).
 *
 * Three policies: `pim_wins` / `remote_wins` pin a side; `lww` keeps the most
 * recently updated value. The remote only wins when it is demonstrably newer —
 * a tie, both timestamps missing, or a missing remote timestamp all keep PIM
 * (the system of record); a remote change carrying a timestamp the PIM side
 * lacks counts as newer.
 *
 * Anti-loop: a PIM value whose provenance is `integration` was itself pulled
 * from a remote, so re-pushing it (in → out → in) would loop forever.
 * {@see originatedFromRemote()} lets the outbound path skip those values.
 */
final class ConflictResolver
{
    /**
     * Provenance string of a value written by an inbound sync. Mirrors
     * `App\Catalog\Domain\Provenance::Integration->value` without importing the
     * Catalog domain enum (ADR-0022 cross-BC boundary).
     */
    public const string INTEGRATION_PROVENANCE = 'integration';

    public function winner(
        ConflictPolicy $policy,
        ?DateTimeImmutable $pimUpdatedAt,
        ?DateTimeImmutable $remoteUpdatedAt,
    ): ConflictWinner {
        return match ($policy) {
            ConflictPolicy::PimWins => ConflictWinner::Pim,
            ConflictPolicy::RemoteWins => ConflictWinner::Remote,
            ConflictPolicy::Lww => $this->lastWriteWins($pimUpdatedAt, $remoteUpdatedAt),
        };
    }

    /**
     * Whether a PIM value with this provenance originated from an inbound sync
     * (and must not be pushed back out — anti-loop).
     */
    public function originatedFromRemote(string $provenance): bool
    {
        return self::INTEGRATION_PROVENANCE === $provenance;
    }

    private function lastWriteWins(?DateTimeImmutable $pim, ?DateTimeImmutable $remote): ConflictWinner
    {
        // Remote only wins when it is demonstrably newer than PIM (the system of
        // record): equal timestamps, both missing, or a missing remote timestamp
        // all keep PIM. A remote change with a timestamp the PIM side lacks counts
        // as newer.
        if (null === $remote) {
            return ConflictWinner::Pim;
        }
        if (null === $pim) {
            return ConflictWinner::Remote;
        }

        return $remote > $pim ? ConflictWinner::Remote : ConflictWinner::Pim;
    }
}
