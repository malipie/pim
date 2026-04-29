<?php

declare(strict_types=1);

namespace App\Catalog\Application\Validation\TypeValidator;

use App\Catalog\Application\Validation\AttributeValueValidatorInterface;
use App\Catalog\Application\Validation\ValidationError;
use App\Catalog\Domain\Entity\Attribute;
use Symfony\Component\Uid\Uuid;

/**
 * `relation` AttributeType validator — value points at a CatalogObject.
 *
 * Schema-shape only: `value.object_id` must be a UUID. Existence + kind
 * cross-check (`relation` rules can pin which kinds are acceptable
 * targets via `value.allowed_kinds`) lives at the API layer in #41.
 */
final class RelationValidator implements AttributeValueValidatorInterface
{
    public function validate(Attribute $attribute, array $value): array
    {
        $raw = $value['object_id'] ?? null;
        if (!\is_string($raw) || !Uuid::isValid($raw)) {
            return [new ValidationError('value.object_id', 'relation.expected_uuid', 'Relation value must include a valid object_id UUID.')];
        }

        return [];
    }
}
