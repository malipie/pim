<?php

declare(strict_types=1);

namespace App\Catalog\Application\Validation\TypeValidator;

use App\Catalog\Application\Validation\AttributeValueValidatorInterface;
use App\Catalog\Application\Validation\ValidationError;
use App\Catalog\Domain\Entity\Attribute;

/**
 * `textarea` AttributeType validator (#1177).
 *
 * Multi-line plain text without HTML — distinct from `wysiwyg`. Rules
 * from `Attribute.validation_rules`:
 *   - `max_length` (int): UTF-8 character cap
 *   - `min_length` (int): UTF-8 character floor
 *
 * Unlike `text` there is no `pattern` rule: a free-form description is
 * not a candidate for regex shaping.
 */
final class TextareaValidator implements AttributeValueValidatorInterface
{
    public function validate(Attribute $attribute, array $value): array
    {
        $raw = $value['value'] ?? null;

        if (!\is_string($raw)) {
            return [new ValidationError('value.value', 'textarea.expected_string', 'Textarea value must be a string.')];
        }

        $errors = [];
        $rules = $attribute->getValidationRules();
        $length = mb_strlen($raw, 'UTF-8');

        $max = $rules['max_length'] ?? null;
        if (\is_int($max) && $length > $max) {
            $errors[] = new ValidationError('value.value', 'textarea.too_long', \sprintf('Text exceeds max_length=%d (got %d).', $max, $length));
        }
        $min = $rules['min_length'] ?? null;
        if (\is_int($min) && $length < $min) {
            $errors[] = new ValidationError('value.value', 'textarea.too_short', \sprintf('Text shorter than min_length=%d (got %d).', $min, $length));
        }

        return $errors;
    }
}
