<?php

declare(strict_types=1);

namespace App\Export\Application\Builder;

/**
 * Parsed export column specification.
 *
 * Three kinds of columns drive every export row:
 *   - `built_in`: `sku` (CatalogObject.code), `name` (built-in name
 *     attribute), `parent_sku` (parent CatalogObject's code), `category`
 *     (concatenated category names).
 *   - `attribute`: any catalog attribute referenced by its code. Optional
 *     `.locale` or `.channel` suffix narrows to a localisable / scopable
 *     variant of `ObjectValue` (PRD §6.1, §8.3, ADR-006 hybrid model).
 *   - `system`: synthetic columns computed from the row itself
 *     (e.g. `created_at`, `updated_at`).
 *
 * The original column key (`sku`, `description.pl`, `price.shopify`)
 * survives as `$key` so the writer can use it as the XLSX header verbatim
 * — Magda's round-trip relies on stable column names between export and
 * reimport.
 */
final class ColumnDefinition
{
    public const KIND_BUILT_IN = 'built_in';
    public const KIND_ATTRIBUTE = 'attribute';
    public const KIND_SYSTEM = 'system';

    public function __construct(
        public readonly string $key,
        public readonly string $kind,
        public readonly string $code,
        public readonly ?string $locale = null,
        public readonly ?string $channel = null,
    ) {
    }

    public function isAttribute(): bool
    {
        return self::KIND_ATTRIBUTE === $this->kind;
    }

    public function isBuiltIn(): bool
    {
        return self::KIND_BUILT_IN === $this->kind;
    }

    public function isSystem(): bool
    {
        return self::KIND_SYSTEM === $this->kind;
    }
}
