<?php

declare(strict_types=1);

namespace App\Catalog\Application\Validation\TypeValidator;

use App\Catalog\Application\Validation\AttributeValueValidatorInterface;
use App\Catalog\Application\Validation\ValidationError;
use App\Catalog\Domain\Entity\Attribute;

use const FILTER_VALIDATE_EMAIL;

/**
 * `email` AttributeType validator (#1177).
 *
 * Stores the address as a string in `object_values.value->>'value'`.
 * Format is checked with PHP's `FILTER_VALIDATE_EMAIL` (RFC 5322-lite).
 * Optional rule from `Attribute.validation_rules`:
 *   - `pattern` (string): extra PCRE the address must match (e.g. a
 *     corporate domain allow-list).
 */
final class EmailValidator implements AttributeValueValidatorInterface
{
    public function validate(Attribute $attribute, array $value): array
    {
        $raw = $value['value'] ?? null;
        if (!\is_string($raw) || '' === $raw) {
            return [new ValidationError('value.value', 'email.expected_string', 'Email value must be a non-empty string.')];
        }

        if (false === filter_var($raw, FILTER_VALIDATE_EMAIL)) {
            return [new ValidationError('value.value', 'email.invalid', \sprintf('Value "%s" is not a valid email address.', $raw))];
        }

        $errors = [];
        $pattern = $attribute->getValidationRules()['pattern'] ?? null;
        if (\is_string($pattern) && '' !== $pattern && 1 !== preg_match($pattern, $raw)) {
            $errors[] = new ValidationError('value.value', 'email.pattern_mismatch', \sprintf('Email does not match pattern %s.', $pattern));
        }

        return $errors;
    }
}
