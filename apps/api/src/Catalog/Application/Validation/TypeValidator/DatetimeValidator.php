<?php

declare(strict_types=1);

namespace App\Catalog\Application\Validation\TypeValidator;

use App\Catalog\Application\Validation\AttributeValueValidatorInterface;
use App\Catalog\Application\Validation\ValidationError;
use App\Catalog\Domain\Entity\Attribute;

/**
 * `datetime` AttributeType validator (#1177).
 *
 * Accepts ISO 8601 strings carrying a time component (the admin form
 * sends `YYYY-MM-DDTHH:mm` from `<input type="datetime-local">`); a
 * date-only string also parses. Mirrors {@see DateValidator} including
 * the `min` / `max` rule names (ISO 8601 strings) so the two sibling
 * temporal validators stay consistent.
 */
final class DatetimeValidator implements AttributeValueValidatorInterface
{
    public function validate(Attribute $attribute, array $value): array
    {
        $raw = $value['value'] ?? null;
        if (!\is_string($raw) || '' === $raw) {
            return [new ValidationError('value.value', 'datetime.expected_iso_string', 'Datetime value must be a non-empty ISO 8601 string.')];
        }

        $parsed = date_create_immutable($raw);
        if (false === $parsed) {
            return [new ValidationError('value.value', 'datetime.unparseable', \sprintf('Datetime "%s" is not a valid ISO 8601 string.', $raw))];
        }

        $errors = [];
        $rules = $attribute->getValidationRules();
        if (\is_string($rules['min'] ?? null)) {
            $min = date_create_immutable($rules['min']);
            if (false !== $min && $parsed < $min) {
                $errors[] = new ValidationError('value.value', 'datetime.below_min', \sprintf('Datetime %s is before min %s.', $raw, $rules['min']));
            }
        }
        if (\is_string($rules['max'] ?? null)) {
            $max = date_create_immutable($rules['max']);
            if (false !== $max && $parsed > $max) {
                $errors[] = new ValidationError('value.value', 'datetime.above_max', \sprintf('Datetime %s is after max %s.', $raw, $rules['max']));
            }
        }

        return $errors;
    }
}
