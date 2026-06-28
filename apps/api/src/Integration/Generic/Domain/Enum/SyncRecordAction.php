<?php

declare(strict_types=1);

namespace App\Integration\Generic\Domain\Enum;

/**
 * The outcome recorded for a single record in a {@see \App\Integration\Generic\Domain\Entity\SyncRunLog}
 * (ADR-0022, epic APIC, ticket APIC-P3-02). `failed` is the per-record error
 * state; the others are successful upsert/skip outcomes.
 */
enum SyncRecordAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Skipped = 'skipped';
    case Failed = 'failed';
}
