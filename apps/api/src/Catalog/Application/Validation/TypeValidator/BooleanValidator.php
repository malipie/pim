<?php

declare(strict_types=1);

namespace App\Catalog\Application\Validation\TypeValidator;

use App\Catalog\Application\Validation\AttributeValueValidatorInterface;
use App\Catalog\Application\Validation\ValidationError;
use App\Catalog\Domain\Entity\Attribute;

/**
 * `boolean` AttributeType validator. Accepts strict bool only — `1`,
 * `'true'`, `'on'` etc. are explicitly rejected to keep the JSONB
 * payload deterministic.
 */
final class BooleanValidator implements AttributeValueValidatorInterface
{
    public function validate(Attribute $attribute, array $value): array
    {
        $raw = $value['value'] ?? null;
        if (!\is_bool($raw)) {
            return [new ValidationError('value.value', 'boolean.expected_bool', 'Boolean value must be true or false (strict).')];
        }

        return [];
    }
}
