<?php

declare(strict_types=1);

namespace App\Catalog\Application\Validation\TypeValidator;

use App\Catalog\Application\Validation\AttributeValueValidatorInterface;
use App\Catalog\Application\Validation\ValidationError;
use App\Catalog\Domain\Entity\Attribute;

/**
 * `metric` AttributeType validator. Shape: `{value: numeric, unit: 'kg' | 'cm' | …}`.
 *
 * Rules:
 *   - `units` (list<string>) — restrict the allowed unit set
 *   - `min` / `max` (numeric) — bounds on the numeric value
 *   - `decimal_precision` (int >=0) — max decimal places
 *
 * Unit family normalization (kg ↔ g, cm ↔ m, …) is the API layer's job —
 * the validator only checks membership in the configured `units` set.
 */
final class MetricValidator implements AttributeValueValidatorInterface
{
    public function validate(Attribute $attribute, array $value): array
    {
        $errors = [];
        $raw = $value['value'] ?? null;
        $unit = $value['unit'] ?? null;

        if (!\is_int($raw) && !\is_float($raw)) {
            $errors[] = new ValidationError('value.value', 'metric.expected_numeric_value', 'Metric value must be int or float.');
        }
        if (!\is_string($unit) || '' === $unit) {
            $errors[] = new ValidationError('value.unit', 'metric.expected_unit', 'Metric unit must be a non-empty string.');
        }

        $rules = $attribute->getValidationRules();
        $allowed = $rules['units'] ?? null;
        if (\is_string($unit) && \is_array($allowed) && !\in_array($unit, $allowed, true)) {
            $errors[] = new ValidationError('value.unit', 'metric.unsupported_unit', \sprintf('Unit "%s" is not in the configured units set.', $unit));
        }

        $min = $rules['min'] ?? null;
        if ((\is_int($raw) || \is_float($raw)) && (\is_int($min) || \is_float($min)) && $raw < $min) {
            $errors[] = new ValidationError('value.value', 'metric.below_min', \sprintf('Metric value %s is below min %s.', (string) $raw, (string) $min));
        }
        $max = $rules['max'] ?? null;
        if ((\is_int($raw) || \is_float($raw)) && (\is_int($max) || \is_float($max)) && $raw > $max) {
            $errors[] = new ValidationError('value.value', 'metric.above_max', \sprintf('Metric value %s exceeds max %s.', (string) $raw, (string) $max));
        }
        $precision = $rules['decimal_precision'] ?? null;
        if (\is_int($precision) && $precision >= 0 && \is_float($raw)) {
            $rounded = round($raw, $precision);
            if (abs($rounded - $raw) > 1e-9) {
                $errors[] = new ValidationError('value.value', 'metric.precision_exceeded', \sprintf('Metric value has more than %d decimal places.', $precision));
            }
        }

        return $errors;
    }
}
