<?php

declare(strict_types=1);

namespace App\Import\Domain\Enum;

/**
 * ADR-0019 / IMP2-1.3 (#1465) — write strategy of an import run.
 *
 * CREATE skips rows whose match key already exists, UPDATE skips rows
 * without a match, UPSERT (default) branches per row. Legacy values
 * (ADD/MERGE/INCREMENT/DELETE) were never implemented and are mapped
 * away by migration Version20260612230000.
 */
enum ImportMode: string
{
    case Create = 'CREATE';
    case Update = 'UPDATE';
    case Upsert = 'UPSERT';
}
