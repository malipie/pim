<?php

declare(strict_types=1);

namespace App\Import\Domain\Enum;

/**
 * VIEW-IMP-02 (#498) — write strategy a profile applies to its target.
 *
 * Surfaced as a `ModeBadge` in the imports hub. The worker today
 * always upserts; `ADD` / `DELETE` are reserved for the bulk
 * operations follow-up.
 */
enum ImportMode: string
{
    case Add = 'ADD';
    case Update = 'UPDATE';
    case Upsert = 'UPSERT';
    case Merge = 'MERGE';
    case Increment = 'INCREMENT';
    case Delete = 'DELETE';
}
