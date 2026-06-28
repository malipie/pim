<?php

declare(strict_types=1);

namespace App\Integration\Generic\Domain\Enum;

/**
 * The kind of delta-sync cursor a {@see \App\Integration\Generic\Domain\Entity\SyncBinding}
 * tracks (ADR-0022, epic APIC, ticket APIC-P3-03).
 *
 * `updated_at` is a timestamp watermark, `incremental_id` a monotonic integer,
 * `opaque` a server-supplied token whose ordering the remote owns (so the
 * cursor manager accepts any new opaque value).
 */
enum CursorType: string
{
    case UpdatedAt = 'updated_at';
    case IncrementalId = 'incremental_id';
    case Opaque = 'opaque';

    /** Whether the manager can compare two values of this type for forward progress. */
    public function isComparable(): bool
    {
        return self::Opaque !== $this;
    }
}
