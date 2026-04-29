<?php

declare(strict_types=1);

namespace App\Catalog\Application\Validation\TypeValidator;

use App\Catalog\Application\Validation\AttributeValueValidatorInterface;
use App\Catalog\Application\Validation\ValidationError;
use App\Catalog\Domain\Entity\Attribute;

/**
 * `number` AttributeType validator. Accepts int + float.
 *
 * Rules: `min` (numeric), `max` (numeric), `decimal_precision` (int >=0).
 */
final class NumberValidator implements AttributeValueValidatorInterface
{
    public function validate(Attribute $attribute, array $value): array
    {
        $errors = [];
        $raw = $value['value'] ?? null;

        if (!\is_int($raw) && !\is_float($raw)) {
            return [new ValidationError('value.value', 'number.expected_numeric', 'Number value must be int or float.')];
        }

        $rules = $attribute->getValidationRules();
        $min = $rules['min'] ?? null;
        if ((\is_int($min) || \is_float($min)) && $raw < $min) {
            $errors[] = new ValidationError('value.value', 'number.below_min', \sprintf('Value %s is below min %s.', (string) $raw, (string) $min));
        }
        $max = $rules['max'] ?? null;
        if ((\is_int($max) || \is_float($max)) && $raw > $max) {
            $errors[] = new ValidationError('value.value', 'number.above_max', \sprintf('Value %s exceeds max %s.', (string) $raw, (string) $max));
        }
        $precision = $rules['decimal_precision'] ?? null;
        if (\is_int($precision) && $precision >= 0 && \is_float($raw)) {
            $rounded = round($raw, $precision);
            if (abs($rounded - $raw) > 1e-9) {
                $errors[] = new ValidationError('value.value', 'number.precision_exceeded', \sprintf('Value has more than %d decimal places.', $precision));
            }
        }

        return $errors;
    }
}
