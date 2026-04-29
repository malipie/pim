<?php

declare(strict_types=1);

namespace App\Catalog\Application\Validation\TypeValidator;

use App\Catalog\Application\Validation\AttributeValueValidatorInterface;
use App\Catalog\Application\Validation\ValidationError;
use App\Catalog\Domain\Entity\Attribute;

/**
 * `select` AttributeType validator.
 *
 * Rule: `option_codes` (list<string>) — when configured, the picked
 * value must match one of these. When the rule is missing the
 * validator only checks the shape; the API layer (#41) is expected to
 * cross-check against the live AttributeOption rows from the database.
 */
final class SelectValidator implements AttributeValueValidatorInterface
{
    public function validate(Attribute $attribute, array $value): array
    {
        $code = $value['option_code'] ?? null;
        if (!\is_string($code) || '' === $code) {
            return [new ValidationError('value.option_code', 'select.expected_string', 'Select value must include a non-empty option_code.')];
        }

        $rules = $attribute->getValidationRules();
        $allowed = $rules['option_codes'] ?? null;
        if (\is_array($allowed) && !\in_array($code, $allowed, true)) {
            return [new ValidationError(
                'value.option_code',
                'select.unknown_option',
                \sprintf('Option "%s" is not in the configured option_codes set.', $code),
            )];
        }

        return [];
    }
}
