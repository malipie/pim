<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Validator;

use App\Catalog\Domain\Entity\Attribute;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * ADR-014 / MOD-08 (#900) — validates per-link metadata payloads on
 * `object_relations.metadata` against the advanced-field schema declared
 * in `Attribute.validation_rules.advanced_fields`.
 *
 * Schema convention (lives in `validation_rules` JSONB so we don't grow a
 * second JSONB column on `attributes`):
 *
 *   {
 *     "advanced_fields": [
 *       {"code": "priority",    "type": "number",  "label": {...}, "required": true},
 *       {"code": "recommended", "type": "boolean", "label": {...}, "required": false}
 *     ]
 *   }
 *
 * Supported field types in MOD-08: `text`, `number`, `boolean`. Richer
 * types (`select`, `asset`) land in follow-up when the FE editor for
 * advanced metadata is built (MOD-12).
 *
 * `advanced=false` attribute → metadata payload MUST be empty. The
 * validator rejects stray fields up front so the persistence layer never
 * sees half-defined relation rows.
 */
final readonly class RelationMetadataValidator
{
    private const array SUPPORTED_FIELD_TYPES = ['text', 'number', 'boolean'];

    /**
     * @param array<string, mixed> $metadata
     *
     * @return array<string, mixed> the sanitised payload (extra keys
     *                              stripped, types coerced when safe)
     */
    public function validateAndNormalise(Attribute $attribute, array $metadata): array
    {
        if (!$attribute->isRelationAdvanced()) {
            if ([] !== $metadata) {
                throw new UnprocessableEntityHttpException(\sprintf(
                    'Attribute "%s" is not advanced; metadata payload must be empty.',
                    $attribute->getCode(),
                ));
            }

            return [];
        }

        $fields = $this->extractAdvancedFields($attribute);
        $byCode = [];
        foreach ($fields as $field) {
            $byCode[$field['code']] = $field;
        }

        // Reject unknown keys up front so the caller knows immediately
        // when the schema doesn't match.
        foreach (array_keys($metadata) as $key) {
            if (!isset($byCode[$key])) {
                throw new UnprocessableEntityHttpException(\sprintf(
                    'Unknown metadata field "%s" on attribute "%s".',
                    $key,
                    $attribute->getCode(),
                ));
            }
        }

        $sanitised = [];
        foreach ($byCode as $code => $field) {
            $isPresent = \array_key_exists($code, $metadata);
            if (!$isPresent) {
                if ($field['required']) {
                    throw new UnprocessableEntityHttpException(\sprintf(
                        'Metadata field "%s" is required on attribute "%s".',
                        $code,
                        $attribute->getCode(),
                    ));
                }
                continue;
            }

            $sanitised[$code] = $this->coerceValue($code, $field['type'], $metadata[$code], $attribute);
        }

        return $sanitised;
    }

    /**
     * @return list<array{code: string, type: string, label: array<string, string>, required: bool}>
     */
    private function extractAdvancedFields(Attribute $attribute): array
    {
        $rules = $attribute->getValidationRules();
        $raw = $rules['advanced_fields'] ?? [];
        if (!\is_array($raw)) {
            return [];
        }

        $fields = [];
        foreach ($raw as $entry) {
            if (!\is_array($entry)) {
                continue;
            }
            $code = $entry['code'] ?? null;
            $type = $entry['type'] ?? null;
            if (!\is_string($code) || '' === $code || !\is_string($type)) {
                continue;
            }
            if (!\in_array($type, self::SUPPORTED_FIELD_TYPES, true)) {
                continue;
            }
            $label = $entry['label'] ?? [];
            if (!\is_array($label)) {
                $label = [];
            }
            $labelMap = [];
            foreach ($label as $locale => $text) {
                if (\is_string($locale) && \is_string($text)) {
                    $labelMap[$locale] = $text;
                }
            }
            $fields[] = [
                'code' => $code,
                'type' => $type,
                'label' => $labelMap,
                'required' => (bool) ($entry['required'] ?? false),
            ];
        }

        return $fields;
    }

    private function coerceValue(string $code, string $type, mixed $raw, Attribute $attribute): mixed
    {
        return match ($type) {
            'text' => $this->expectString($code, $raw, $attribute),
            'number' => $this->expectNumber($code, $raw, $attribute),
            'boolean' => $this->expectBoolean($code, $raw, $attribute),
            default => throw new UnprocessableEntityHttpException(\sprintf(
                'Unsupported metadata field type "%s" on attribute "%s".',
                $type,
                $attribute->getCode(),
            )),
        };
    }

    private function expectString(string $code, mixed $raw, Attribute $attribute): string
    {
        if (!\is_string($raw)) {
            throw new UnprocessableEntityHttpException(\sprintf(
                'Metadata field "%s" on attribute "%s" expects a string.',
                $code,
                $attribute->getCode(),
            ));
        }

        return $raw;
    }

    private function expectNumber(string $code, mixed $raw, Attribute $attribute): int|float
    {
        if (\is_int($raw) || \is_float($raw)) {
            return $raw;
        }
        throw new UnprocessableEntityHttpException(\sprintf(
            'Metadata field "%s" on attribute "%s" expects a number.',
            $code,
            $attribute->getCode(),
        ));
    }

    private function expectBoolean(string $code, mixed $raw, Attribute $attribute): bool
    {
        if (\is_bool($raw)) {
            return $raw;
        }
        throw new UnprocessableEntityHttpException(\sprintf(
            'Metadata field "%s" on attribute "%s" expects a boolean.',
            $code,
            $attribute->getCode(),
        ));
    }
}
