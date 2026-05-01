<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Rule;

use InvalidArgumentException;

/**
 * UI-08.8 (#263) — value object encapsulating the per-AttributeGroupAttribute
 * visibility rule.
 *
 * MVP only supports a single `equals` condition; ADR-012 + epik plan §10.6
 * commit to AND/OR composites in Faza 1 and complex date/numeric expressions
 * in Faza 2. The factory methods (`fromArray`, `equals`) constrain that
 * surface — anything that does not match the MVP shape throws at the
 * domain edge so the JSONB column never carries garbage.
 *
 * Equality semantics for `equals`:
 *   - scalars compared with `===` after normalisation (booleans pass
 *     through, numbers as-is, strings case-sensitive).
 *   - arrays compared with `==` (deep equality regardless of key order).
 *   - missing field → false (the rule's referenced attribute has no
 *     value yet, so the dependent attribute stays hidden).
 */
final readonly class VisibleWhenRule
{
    public const string OPERATOR_EQUALS = 'equals';

    /**
     * @param array<string>|null $allowedOperators allowlist hook for Faza 1+ extensions
     */
    public function __construct(
        public string $field,
        public string $operator,
        public mixed $value,
        ?array $allowedOperators = null,
    ) {
        $allowed = $allowedOperators ?? [self::OPERATOR_EQUALS];
        if (!\in_array($this->operator, $allowed, true)) {
            throw new InvalidArgumentException(\sprintf(
                'Unsupported visible_when operator "%s" — MVP supports: %s.',
                $this->operator,
                implode(', ', $allowed),
            ));
        }

        if ('' === trim($this->field)) {
            throw new InvalidArgumentException('visible_when.field must be a non-empty attribute code.');
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $field = $payload['field'] ?? null;
        $operator = $payload['operator'] ?? null;
        if (!\is_string($field)) {
            throw new InvalidArgumentException('visible_when.field must be a string.');
        }
        if (!\is_string($operator)) {
            throw new InvalidArgumentException('visible_when.operator must be a string.');
        }
        if (!\array_key_exists('value', $payload)) {
            throw new InvalidArgumentException('visible_when.value is required.');
        }

        return new self($field, $operator, $payload['value']);
    }

    public static function equals(string $field, mixed $value): self
    {
        return new self($field, self::OPERATOR_EQUALS, $value);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'operator' => $this->operator,
            'value' => $this->value,
        ];
    }
}
