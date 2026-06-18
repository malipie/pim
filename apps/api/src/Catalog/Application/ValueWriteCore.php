<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Application\Validation\AttributeValueValidator;
use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Validator\IdentifierUniquenessValidator;
use App\Shared\Domain\Tenant;
use Symfony\Component\Uid\Uuid;

/**
 * ADR-0019 / IMP2-1.4 (#1466) — the single rule-set every value write path
 * shares: envelope normalisation to the per-type canon, required/format
 * validation and locale/channel scope routing.
 *
 * Consumers: ObjectAttributesUpserter (per-request, maps violations to HTTP
 * exceptions) and BatchValueWriter (import chunks, collects violations as
 * result issues). Keeping the rules here is what makes "import validates
 * exactly like the admin" true instead of aspirational.
 */
final readonly class ValueWriteCore
{
    /**
     * AUD-032 / W2-1 — the `additionalProperties: false` contract from
     * `docs/api/jsonb-schemas.md` §6: the exact set of keys the canonical
     * envelope of each AttributeType may carry inside `object_values.value`.
     * locale / channel / provenance / provenance_meta are NOT envelope keys —
     * they are dedicated columns on the row — so they never appear here. Any
     * key outside an entry's set is rejected (proto-pollution, stored-XSS via
     * smuggled fields, integration garbage).
     *
     * @var array<string, list<string>>
     */
    private const array ALLOWED_KEYS = [
        AttributeType::Text->value => ['value'],
        AttributeType::Textarea->value => ['value'],
        AttributeType::Wysiwyg->value => ['value'],
        AttributeType::Number->value => ['value'],
        AttributeType::Date->value => ['value'],
        AttributeType::Datetime->value => ['value'],
        AttributeType::Boolean->value => ['value'],
        AttributeType::Color->value => ['value'],
        AttributeType::Email->value => ['value'],
        AttributeType::Identifier->value => ['value'],
        AttributeType::Select->value => ['option_code'],
        AttributeType::Multiselect->value => ['option_codes'],
        AttributeType::Price->value => ['amount', 'currency'],
        AttributeType::Metric->value => ['value', 'unit'],
        AttributeType::Asset->value => ['asset_id'],
        AttributeType::Relation->value => ['object_id'],
        AttributeType::Reference->value => ['object_id'],
    ];

    public function __construct(
        private AttributeValueValidator $valueValidator,
        private IdentifierUniquenessValidator $identifierUniqueness,
    ) {
    }

    /**
     * Wrap + canonicalise a raw value into the ADR-0019 per-type envelope.
     *
     * @return array<string, mixed>
     */
    public function normalise(AttributeType $type, mixed $rawValue): array
    {
        if (\is_array($rawValue)) {
            $normalised = [];
            foreach ($rawValue as $key => $value) {
                $normalised[(string) $key] = $value;
            }

            return $this->canonicalise($type, $normalised);
        }

        return $this->canonicalise($type, ['value' => $rawValue]);
    }

    /**
     * #1350 — required attributes can never be explicitly emptied
     * (booleans exempt: an unchecked box IS `false`).
     *
     * @param array<string, mixed> $envelope
     */
    public function requiredViolation(Attribute $attribute, array $envelope): ?string
    {
        if (AttributeType::Boolean !== $attribute->getType()
            && $attribute->isRequired() && self::isEmptyEnvelope($envelope)) {
            return \sprintf('Attribute "%s" is required and cannot be empty.', $attribute->getCode());
        }

        return null;
    }

    /**
     * #1216 / #1261 / AUD-032 — per-type format + option-membership validation,
     * enforced for EVERY AttributeType (the contract in jsonb-schemas.md §6,
     * not just the five legacy `VALUE_VALIDATED_TYPES`). Two layers:
     *
     *   1. `additionalProperties: false` — reject any envelope key the type's
     *      canon does not allow. Runs even for a non-content payload like
     *      `{__proto__: ...}` so smuggled fields never reach JSONB.
     *   2. The per-type value validator (numeric for number, ISO currency for
     *      price, strict bool for boolean, …). Skipped only when the envelope
     *      is an empty clear (clearing a value is always allowed) or the type
     *      has no validator registered (`reference`, a system/listener-written
     *      type whose shape is covered by layer 1).
     *
     * @param array<string, mixed> $envelope
     *
     * @return list<string> violation messages
     */
    public function formatViolations(Attribute $attribute, array $envelope): array
    {
        $type = $attribute->getType();

        $unknownKey = $this->unknownKeyViolation($type, $envelope);
        if (null !== $unknownKey) {
            return [$unknownKey];
        }

        if (!self::hasValidatableContent($type, $envelope) || !$this->hasValidator($type)) {
            return [];
        }

        $messages = [];
        foreach ($this->valueValidator->validate($attribute, $envelope) as $error) {
            $messages[] = $error->message;
        }

        return $messages;
    }

    /**
     * AUD-032 — `additionalProperties: false`: the first envelope key outside
     * the type's canonical set, as a violation message, or null when the
     * envelope only carries allowed keys. ALLOWED_KEYS covers every
     * AttributeType, so a new enum case added without a canon entry trips a
     * PHPStan error here rather than silently skipping the check.
     *
     * @param array<string, mixed> $envelope
     */
    private function unknownKeyViolation(AttributeType $type, array $envelope): ?string
    {
        $allowed = self::ALLOWED_KEYS[$type->value];

        foreach (array_keys($envelope) as $key) {
            if (!\in_array($key, $allowed, true)) {
                return \sprintf(
                    'Unexpected key "%s" for attribute type "%s"; allowed: %s.',
                    $key,
                    $type->value,
                    implode(', ', $allowed),
                );
            }
        }

        return null;
    }

    private function hasValidator(AttributeType $type): bool
    {
        // `reference` is system-only (created_by / updated_by, stamped by
        // Doctrine listeners) and carries no AttributeValueValidator — running
        // the dispatcher would yield a spurious "unsupported_type". Its shape
        // ({object_id}) is already guarded by the additionalProperties check.
        return AttributeType::Reference !== $type;
    }

    /**
     * #1179 — identifier uniqueness pre-check (DB partial unique index stays
     * the race-proof backstop). Returns the duplicate value, or null.
     *
     * @param array<string, mixed> $envelope
     */
    public function duplicateIdentifier(CatalogObject $object, Attribute $attribute, array $envelope): ?string
    {
        if (AttributeType::Identifier !== $attribute->getType()) {
            return null;
        }
        $candidate = $envelope['value'] ?? null;
        if (\is_string($candidate) && '' !== $candidate
            && $this->identifierUniqueness->isDuplicate($object, $attribute, $candidate)) {
            return $candidate;
        }

        return null;
    }

    /**
     * #1148/#1154 — locale/channel scope routing: non-primary locale on a
     * localizable attribute targets that locale row, otherwise global;
     * channel only applies to scopable attributes.
     *
     * @return array{0: ?string, 1: ?Uuid} [locale, channelId]
     */
    public function routeScope(Attribute $attribute, Tenant $tenant, ?string $locale, ?Uuid $channelId): array
    {
        $isNonPrimaryLocale = null !== $locale && $locale !== $tenant->getPrimaryLocale();
        $targetLocale = $isNonPrimaryLocale && $attribute->isLocalizable() ? $locale : null;
        $targetChannel = null !== $channelId && $attribute->isScopable() ? $channelId : null;

        return [$targetLocale, $targetChannel];
    }

    /**
     * True when the envelope carries something to validate — i.e. it is not an
     * empty clear. Clearing a value (`{}`, `{value: ''}`, `{value: null}`) is
     * always allowed, so the per-type validator is skipped for those; every
     * other shape (including object-shaped asset / relation / price / metric
     * payloads, not just `{value}`) is validated. AUD-032 widened this from the
     * old select/multiselect/`value`-only special-casing.
     *
     * @param array<string, mixed> $envelope
     */
    public static function hasValidatableContent(AttributeType $type, array $envelope): bool
    {
        return !self::isEmptyEnvelope($envelope);
    }

    /**
     * #1350 — true when every leaf is null / '' / []. Booleans and zeros
     * are values.
     */
    public static function isEmptyEnvelope(mixed $value): bool
    {
        if (null === $value || '' === $value || [] === $value) {
            return true;
        }
        if (\is_array($value)) {
            foreach ($value as $leaf) {
                if (!self::isEmptyEnvelope($leaf)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * ADR-0019 / IMP2-1.2 (#1464) — normalise legacy `{value: X}` wraps and
     * bare multiselect lists into the per-type canonical envelope.
     *
     * @param array<array-key, mixed> $envelope
     *
     * @return array<string, mixed>
     */
    private function canonicalise(AttributeType $type, array $envelope): array
    {
        if (AttributeType::Multiselect === $type && array_is_list($envelope)) {
            return ['option_codes' => $envelope];
        }

        /** @var array<string, mixed> $envelope non-list past the guard (normalise stringifies keys) */
        if (!\array_key_exists('value', $envelope)) {
            return $envelope;
        }

        $value = $envelope['value'];
        $rest = $envelope;
        unset($rest['value']);

        return match ($type) {
            AttributeType::Select => \is_string($value) ? ['option_code' => $value] + $rest : $envelope,
            AttributeType::Multiselect => \is_array($value) ? ['option_codes' => array_values($value)] + $rest : $envelope,
            AttributeType::Price => \is_int($value) || \is_float($value) || (\is_string($value) && is_numeric($value))
                ? ['amount' => \is_string($value) ? (float) $value : $value] + $rest
                : $envelope,
            default => $envelope,
        };
    }
}
