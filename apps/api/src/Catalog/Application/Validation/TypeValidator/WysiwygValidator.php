<?php

declare(strict_types=1);

namespace App\Catalog\Application\Validation\TypeValidator;

use App\Catalog\Application\Validation\AttributeValueValidatorInterface;
use App\Catalog\Application\Validation\ValidationError;
use App\Catalog\Domain\Entity\Attribute;

/**
 * VIEW-07.2 (#423) — `wysiwyg` AttributeType validator.
 *
 * The frontend (Plate editor, `@udecode/plate`) serialises the
 * document to an HTML string and submits it under the standard
 * `value.value` key. This validator only enforces the structural
 * shape (must be a string) and an optional length cap; HTML
 * sanitisation is enforced at render time on the frontend (DOMPurify
 * before `dangerouslySetInnerHTML`) — keeping the backend agnostic of
 * the formatting library means the same enum can later be re-pointed
 * at TipTap/Lexical without rewriting the contract.
 *
 * Rules from `Attribute.validation_rules`:
 *   - `max_length` (int): UTF-8 character cap on the HTML string.
 */
final class WysiwygValidator implements AttributeValueValidatorInterface
{
    public function validate(Attribute $attribute, array $value): array
    {
        $errors = [];
        $raw = $value['value'] ?? null;

        if (!\is_string($raw)) {
            return [new ValidationError('value.value', 'wysiwyg.expected_string', 'Wysiwyg value must be a string (HTML).')];
        }

        $rules = $attribute->getValidationRules();
        $max = $rules['max_length'] ?? null;
        if (\is_int($max)) {
            $length = mb_strlen($raw, 'UTF-8');
            if ($length > $max) {
                $errors[] = new ValidationError(
                    'value.value',
                    'wysiwyg.too_long',
                    \sprintf('Wysiwyg HTML exceeds max_length=%d (got %d).', $max, $length),
                );
            }
        }

        return $errors;
    }
}
