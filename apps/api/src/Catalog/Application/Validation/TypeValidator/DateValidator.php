<?php

declare(strict_types=1);

namespace App\Catalog\Application\Validation\TypeValidator;

use App\Catalog\Application\Validation\AttributeValueValidatorInterface;
use App\Catalog\Application\Validation\ValidationError;
use App\Catalog\Domain\Entity\Attribute;

/**
 * `date` AttributeType validator. Accepts ISO 8601 strings (YYYY-MM-DD
 * or full date-time). Rules: `min` / `max` (ISO 8601 strings).
 */
final class DateValidator implements AttributeValueValidatorInterface
{
    public function validate(Attribute $attribute, array $value): array
    {
        $raw = $value['value'] ?? null;
        if (!\is_string($raw) || '' === $raw) {
            return [new ValidationError('value.value', 'date.expected_iso_string', 'Date value must be a non-empty ISO 8601 string.')];
        }

        $parsed = date_create_immutable($raw);
        if (false === $parsed) {
            return [new ValidationError('value.value', 'date.unparseable', \sprintf('Date "%s" is not a valid ISO 8601 string.', $raw))];
        }

        $errors = [];
        $rules = $attribute->getValidationRules();
        if (\is_string($rules['min'] ?? null)) {
            $min = date_create_immutable($rules['min']);
            if (false !== $min && $parsed < $min) {
                $errors[] = new ValidationError('value.value', 'date.below_min', \sprintf('Date %s is before min %s.', $raw, $rules['min']));
            }
        }
        if (\is_string($rules['max'] ?? null)) {
            $max = date_create_immutable($rules['max']);
            if (false !== $max && $parsed > $max) {
                $errors[] = new ValidationError('value.value', 'date.above_max', \sprintf('Date %s is after max %s.', $raw, $rules['max']));
            }
        }

        return $errors;
    }
}
