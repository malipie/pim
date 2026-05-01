<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

/**
 * Catalog of attribute types supported in MVP.
 *
 * Per ADR-006 (hybrid attribute model): every catalog attribute carries a
 * type that drives storage, validation, and rendering. The 10 cases below
 * cover MVP needs — adding a new type is a coordinated change across:
 *   - this enum (new case);
 *   - per-type validator (#39 / 0.3.9);
 *   - admin form renderer (#56 / 0.6.3);
 *   - attribute-indexed serializer (#38 / 0.3.8).
 *
 * `usesOptions()` is the only behaviour exposed in #31. It powers invariant
 * checks ("an attribute of type=number cannot have AttributeOption rows")
 * that #39 will enforce at the validator layer; storing it as enum logic
 * keeps the answer next to the cases.
 *
 * The enum is `string`-backed so the value lives in Postgres as VARCHAR,
 * round-trips through Doctrine's `enumType:` mapping, and stays grep-able
 * in fixtures + integration calls.
 */
enum AttributeType: string
{
    case Text = 'text';
    case Number = 'number';
    case Select = 'select';
    case Multiselect = 'multiselect';
    case Date = 'date';
    case Boolean = 'boolean';
    case Asset = 'asset';
    case Relation = 'relation';
    case Price = 'price';
    case Metric = 'metric';

    /**
     * UI-08.3 (#258) — system attribute types. Used by `created_at`/
     * `updated_at` (Datetime) and `created_by`/`updated_by` (Reference, with
     * `validation_rules.target_entity = 'user'` per epik plan §12.2).
     *
     * Read-only in MVP: no AttributeValueValidator binding because system
     * attributes are never written via the catalog write path — they are
     * stamped on the `objects` row by Doctrine listeners and surfaced in the
     * form schema for display only. A future ticket adds renderers; until
     * then the dispatcher's "no validator registered" branch is the safe
     * fallback if anyone ever tries to POST a value against these.
     */
    case Datetime = 'datetime';
    case Reference = 'reference';

    public function usesOptions(): bool
    {
        return self::Select === $this || self::Multiselect === $this;
    }

    public function isSystemType(): bool
    {
        return self::Datetime === $this || self::Reference === $this;
    }
}
