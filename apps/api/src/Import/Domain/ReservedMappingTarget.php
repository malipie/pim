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
     * Assign the row's product to the categories whose `code` matches the
     * cell value (pipe-separated list — IMP2-1.7; first becomes primary,
     * cell order drives `position`). An unresolved code emits a warning per
     * code, not a row-level error.
     */
    public const string CATEGORY = '__category__';

    /**
     * Append variant of {@see CATEGORY} (IMP2-1.7, D2 collection policy):
     * the cell's categories are ADDED to the object's existing assignments
     * (no duplicates) instead of replacing them. Plain `__category__`
     * defaults to replace.
     */
    public const string CATEGORY_APPEND = '__category_append__';

    /**
     * Set the object's publication status from the cell (IMP2-1.7).
     * Allowed literals mirror the exporter: draft|published|archived.
     */
    public const string STATUS = '__status__';

    /**
     * Set the object's enabled flag from the cell (IMP2-1.7).
     * Accepts true|false|1|0.
     */
    public const string ENABLED = '__enabled__';

    /**
     * Link the row's object to its parent (variant → master) by the parent's
     * `code` (IMP2-1.8). Resolved in the two-pass relation step after all
     * objects are written, so variant rows may appear before OR after their
     * master. An unresolved / self / cyclic parent is a row error.
     */
    public const string PARENT_SKU = '__parent_sku__';

    /**
     * Set the master's variant axes (IMP2-1.8). Round-trippable full shape
     * `code:value,value|code:value` — the axis attribute codes plus their
     * option-code values. Applied via {@see \App\Catalog\Domain\Entity\CatalogObject::declareVariantAxes()}.
     */
    public const string VARIANT_AXES = '__variant_axes__';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::SKIP, self::CATEGORY, self::CATEGORY_APPEND, self::STATUS, self::ENABLED, self::PARENT_SKU, self::VARIANT_AXES];
    }

    /** Both category targets (replace + append). */
    public static function isCategory(string $target): bool
    {
        return self::CATEGORY === $target || self::CATEGORY_APPEND === $target;
    }

    public static function isReserved(string $target): bool
    {
        return \in_array($target, self::all(), true);
    }
}
