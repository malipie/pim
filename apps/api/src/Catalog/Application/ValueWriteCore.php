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
     * #1216 — types whose value validator reads the canonical envelope and
     * carries a real format rule. See the original Upserter docblock for why
     * lenient scalar types stay out until backfilled.
     *
     * @var list<AttributeType>
     */
    private const array VALUE_VALIDATED_TYPES = [
        AttributeType::Email,
        AttributeType::Color,
        AttributeType::Identifier,
        AttributeType::Select,
        AttributeType::Multiselect,
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
     * #1216 / #1261 — per-type format + option-membership validation.
     * Empty values are skipped (clearing is always allowed).
     *
     * @param array<string, mixed> $envelope
     *
     * @return list<string> violation messages
     */
    public function formatViolations(Attribute $attribute, array $envelope): array
    {
        if (!\in_array($attribute->getType(), self::VALUE_VALIDATED_TYPES, true)
            || !self::hasValidatableContent($attribute->getType(), $envelope)) {
            return [];
        }

        $messages = [];
        foreach ($this->valueValidator->validate($attribute, $envelope) as $error) {
            $messages[] = $error->message;
        }

        return $messages;
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
     * @param array<string, mixed> $envelope
     */
    public static function hasValidatableContent(AttributeType $type, array $envelope): bool
    {
        if (AttributeType::Select === $type) {
            $optionCode = $envelope['option_code'] ?? null;

            return \is_string($optionCode) && '' !== $optionCode;
        }

        if (AttributeType::Multiselect === $type) {
            $optionCodes = $envelope['option_codes'] ?? null;

            return \is_array($optionCodes) && [] !== $optionCodes;
        }

        $scalar = $envelope['value'] ?? null;

        return null !== $scalar && '' !== $scalar;
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
