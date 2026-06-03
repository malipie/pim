<?php

declare(strict_types=1);

namespace App\Import\Domain;

/**
 * Parses an import column header into its attribute base + optional
 * locale modifier — the import-side mirror of the exporter's
 * {@see \App\Export\Application\Builder\ColumnResolver} grammar.
 *
 * Export emits localised values with a dotted suffix: `name.pl`,
 * `description.pl` (PRD-PIM-exports.md §6.1). Attribute codes are
 * `[a-z0-9_]` and never contain a dot, so the first `.` unambiguously
 * separates the attribute code from its locale modifier.
 *
 * MVP scope (#1130): the suffix is interpreted as a locale. Channel-scoped
 * columns (`description.shopify`) and the combined `locale.channel`
 * notation are deferred to the channels epic (#1147) — until then a
 * channel suffix would be read as a locale, matching the exporter's own
 * single-segment grammar.
 */
final class ColumnHeader
{
    /**
     * The attribute code part of the header (everything before the first
     * dot). `name.pl` → `name`; `price` → `price`.
     */
    public static function baseOf(string $header): string
    {
        $pos = strpos($header, '.');

        return false === $pos ? $header : substr($header, 0, $pos);
    }

    /**
     * The locale modifier (everything after the first dot), or null when
     * the column carries no suffix. `name.pl` → `pl`; `price` → null.
     */
    public static function localeOf(string $header): ?string
    {
        $pos = strpos($header, '.');
        if (false === $pos) {
            return null;
        }
        $modifier = substr($header, $pos + 1);

        return '' === $modifier ? null : $modifier;
    }
}
