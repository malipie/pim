<?php

declare(strict_types=1);

namespace App\Import\Domain;

/**
 * Reserved values for the `mapping` payload that the wizard sends to
 * the import endpoints. They name targets that are NOT regular
 * Attribute codes — the validator + creator route them differently
 * (skip rows, assign categories, …).
 *
 * The double-underscore prefix mirrors the frontend's Combobox
 * convention (see StepMapping.tsx → SKIP_VALUE / CATEGORY_VALUE) and
 * gives a clear visual signal that these strings must never collide
 * with a tenant-defined Attribute code.
 */
final class ReservedMappingTarget
{
    /** Drop the column — do not write its value anywhere. */
    public const string SKIP = 'skip';

    /**
     * Assign the row's product to the category whose `code` matches
     * the cell value (single category per row, becomes primary).
     * Missing category emits a warning, not a row-level error.
     */
    public const string CATEGORY = '__category__';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::SKIP, self::CATEGORY];
    }

    public static function isReserved(string $target): bool
    {
        return \in_array($target, self::all(), true);
    }
}
