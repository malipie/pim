<?php

declare(strict_types=1);

namespace App\Export\Application\Builder;

/**
 * Parses an `export_session.selected_columns` array into
 * {@see ColumnDefinition}s the builder can consume row by row.
 *
 * Column key syntax (PRD-PIM-exports.md §5.3):
 *   - `sku`, `name`, `parent_sku`, `category`, `created_at`, `updated_at`
 *     → built-in / system kind.
 *   - `<attribute_code>` → bare attribute reference (locale=NULL,
 *     channel=NULL — global value).
 *   - `<attribute_code>.<locale>` → localised value (PRD §6.1, audit
 *     contract 4 — `description.pl`).
 *   - `<attribute_code>.<channel>` → channel-scoped value (PRD §6.1 —
 *     `description.shopify`).
 *
 * The parser does NOT verify that the attribute / locale / channel
 * exists — that walidacja lives on the export profile validator
 * (EXP-07). The builder treats unknown attributes as "blank cell" so
 * partial profile staleness (R-47 from PRD §14) degrades gracefully
 * rather than 500-ing the entire job.
 *
 * The grammar is intentionally trivial — one optional `.` segment. PRD
 * deferred channel + locale combined notation (`description.pl.shopify`)
 * to Faza 1; if both ever ship in one column, this resolver is the
 * surface to extend.
 */
final class ColumnResolver
{
    /**
     * Built-in column keys mapped to their semantic code.
     *
     * `category` returns a concatenated string of all ObjectCategory rows
     * for the object (delimiter from {@see ValueSerializer::MULTI_VALUE_GLUE}).
     *
     * @var array<string, string>
     */
    private const BUILT_INS = [
        'sku' => 'sku',
        'parent_sku' => 'parent_sku',
        'category' => 'category',
        'created_at' => 'created_at',
        'updated_at' => 'updated_at',
        'status' => 'status',
        'enabled' => 'enabled',
        'completeness_pct' => 'completeness_pct',
    ];

    /**
     * @param iterable<string> $selectedColumns
     * @param list<string>     $channelCodes    channel codes active for the session (#1229);
     *                                          a `code.modifier` whose modifier is one of these
     *                                          resolves to a channel-scoped column, otherwise locale
     *
     * @return array<int, ColumnDefinition>
     */
    public function resolve(iterable $selectedColumns, array $channelCodes = []): array
    {
        $resolved = [];
        foreach ($selectedColumns as $key) {
            $resolved[] = $this->resolveOne($key, $channelCodes);
        }

        return $resolved;
    }

    /**
     * @param list<string> $channelCodes
     */
    public function resolveOne(string $key, array $channelCodes = []): ColumnDefinition
    {
        if (\array_key_exists($key, self::BUILT_INS)) {
            return new ColumnDefinition(
                key: $key,
                kind: ColumnDefinition::KIND_BUILT_IN,
                code: self::BUILT_INS[$key],
            );
        }

        if (!str_contains($key, '.')) {
            return new ColumnDefinition(
                key: $key,
                kind: ColumnDefinition::KIND_ATTRIBUTE,
                code: $key,
            );
        }

        [$code, $modifier] = explode('.', $key, 2);

        // #1229: a modifier naming one of the session's active channels is a
        // channel-scoped column (`description.shopify`); anything else is a
        // locale (`description.pl`). Disambiguating by membership beats a
        // "short = locale, kebab = channel" heuristic, which would collide on
        // a 2-letter channel code. Combined notation (`description.pl.shopify`)
        // stays deferred (PRD §5.3); `explode(..., 2)` keeps the tail intact.
        if (\in_array($modifier, $channelCodes, true)) {
            return new ColumnDefinition(
                key: $key,
                kind: ColumnDefinition::KIND_ATTRIBUTE,
                code: $code,
                channel: $modifier,
            );
        }

        return new ColumnDefinition(
            key: $key,
            kind: ColumnDefinition::KIND_ATTRIBUTE,
            code: $code,
            locale: $modifier,
        );
    }
}
