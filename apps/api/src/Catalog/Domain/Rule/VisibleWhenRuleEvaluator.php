<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Rule;

/**
 * UI-08.8 (#263) — server-side `visible_when` evaluator.
 *
 * Frontend already evaluates the rule client-side for live form-rendering
 * (`#UI-08.13`). The server-side evaluator powers two paths in MVP:
 *   - API Configurator output filtering (#95) when a profile chooses to
 *     suppress hidden fields from the public payload (follow-up wiring).
 *   - Tests + audit log generation when the operator inspects a frozen
 *     object snapshot.
 *
 * The evaluator reads the materialised `attributes_indexed` JSONB cache
 * from `CatalogObject` rather than walking ObjectValue rows — the cache
 * is the same source the public API serialises, so visibility decisions
 * are consistent with what the integrator sees.
 *
 * `attributes_indexed` carries hybrid payload shapes per ADR-006
 * (`{value: ...}`, `{option_code: ...}`, `{option_codes: [...]}`); the
 * evaluator extracts the canonical scalar before comparing. Comparing
 * the wrapped JSONB structure directly would cause `equals(boolean,
 * true)` to never match for an attribute with shape `{value: true}`.
 */
final class VisibleWhenRuleEvaluator
{
    /**
     * @param array<string, mixed> $attributesIndexed
     */
    public function isVisible(VisibleWhenRule $rule, array $attributesIndexed): bool
    {
        if (!\array_key_exists($rule->field, $attributesIndexed)) {
            return false;
        }

        $current = $this->extractScalar($attributesIndexed[$rule->field]);

        return match ($rule->operator) {
            VisibleWhenRule::OPERATOR_EQUALS => $this->equals($current, $rule->value),
            // Faza 1+ operators (`not_equals`, `in`, `not_in`) materialise here.
            default => true,
        };
    }

    private function equals(mixed $left, mixed $right): bool
    {
        if (\is_array($left) && \is_array($right)) {
            // Deep equality regardless of key order — sort then strict compare.
            $this->sortDeep($left);
            $this->sortDeep($right);

            return $left === $right;
        }

        return $left === $right;
    }

    /**
     * @param array<mixed> $array
     */
    private function sortDeep(array &$array): void
    {
        foreach ($array as &$value) {
            if (\is_array($value)) {
                $this->sortDeep($value);
            }
        }
        unset($value);
        ksort($array);
    }

    private function extractScalar(mixed $payload): mixed
    {
        if (!\is_array($payload)) {
            return $payload;
        }

        if (\array_key_exists('value', $payload)) {
            return $payload['value'];
        }
        if (\array_key_exists('option_code', $payload)) {
            return $payload['option_code'];
        }
        if (\array_key_exists('option_codes', $payload)) {
            return $payload['option_codes'];
        }

        return $payload;
    }
}
