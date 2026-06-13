<?php

declare(strict_types=1);

namespace App\Import\Domain;

/**
 * Read-only / system columns the exporter emits (PRD-PIM-exports.md §5.3
 * built-ins) that must never be re-imported as Attribute values.
 *
 * The round-trip contract (#1130) requires the importer to accept its own
 * export without error. Those columns describe catalog state owned by the
 * platform (timestamps, authorship, denormalised completeness, publication
 * status) — there is no Attribute behind them, so the wizard auto-skips
 * them and the validator never flags them as "unknown" or "missing".
 *
 * `sku` / `category` are deliberately NOT here — they carry meaning on the
 * import side (object code + category assignment). `parent_sku` IS skipped:
 * variant parent linking is out of MVP import scope (see
 * {@see \App\Import\Application\Service\ImportObjectCreator} — "master rows
 * only").
 */
final class SystemColumn
{
    /** @var list<string> */
    // IMP2-1.7 (#1470): `status` / `enabled` removed — they are now explicit,
    // mappable targets (ReservedMappingTarget::STATUS / ENABLED), not auto-skip.
    private const array HEADERS = [
        'parent_sku',
        'completeness_pct',
        'created_at',
        'updated_at',
        'created_by',
        'updated_by',
    ];

    public static function isSystem(string $header): bool
    {
        return \in_array(strtolower(trim($header)), self::HEADERS, true);
    }
}
