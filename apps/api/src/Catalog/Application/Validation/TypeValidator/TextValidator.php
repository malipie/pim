<?php

declare(strict_types=1);

namespace App\Catalog\Application\Validation\TypeValidator;

use App\Catalog\Application\Validation\AttributeValueValidatorInterface;
use App\Catalog\Application\Validation\ValidationError;
use App\Catalog\Domain\Entity\Attribute;

/**
 * `text` AttributeType validator.
 *
 * Rules from `Attribute.validation_rules`:
 *   - `max_length` (int): UTF-8 character cap
 *   - `min_length` (int): UTF-8 character floor
 *   - `pattern` (string): PCRE regex (must match the whole value)
 */
final class TextValidator implements AttributeValueValidatorInterface
{
    public function validate(Attribute $attribute, array $value): array
    {
        $errors = [];
        $raw = $value['value'] ?? null;

        if (!\is_string($raw)) {
            return [new ValidationError('value.value', 'text.expected_string', 'Text value must be a string.')];
        }

        $rules = $attribute->getValidationRules();
        $length = mb_strlen($raw, 'UTF-8');

        $max = $rules['max_length'] ?? null;
        if (\is_int($max) && $length > $max) {
            $errors[] = new ValidationError('value.value', 'text.too_long', \sprintf('Text exceeds max_length=%d (got %d).', $max, $length));
        }
        $min = $rules['min_length'] ?? null;
        if (\is_int($min) && $length < $min) {
            $errors[] = new ValidationError('value.value', 'text.too_short', \sprintf('Text shorter than min_length=%d (got %d).', $min, $length));
        }
        $pattern = $rules['pattern'] ?? null;
        if (\is_string($pattern) && '' !== $pattern && 1 !== preg_match($pattern, $raw)) {
            $errors[] = new ValidationError('value.value', 'text.pattern_mismatch', \sprintf('Text does not match pattern %s.', $pattern));
        }

        return $errors;
    }
}
