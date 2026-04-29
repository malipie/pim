<?php

declare(strict_types=1);

namespace App\Catalog\Application\Validation\TypeValidator;

use App\Catalog\Application\Validation\AttributeValueValidatorInterface;
use App\Catalog\Application\Validation\ValidationError;
use App\Catalog\Domain\Entity\Attribute;
use Symfony\Component\Uid\Uuid;

/**
 * `asset` AttributeType validator — value points at an Asset row.
 *
 * Schema-shape only here: `value.asset_id` must be a valid UUID. Cross-
 * checking that the row actually exists + belongs to the same tenant is
 * the API layer's job (#41) where the database is reachable.
 */
final class AssetValidator implements AttributeValueValidatorInterface
{
    public function validate(Attribute $attribute, array $value): array
    {
        $raw = $value['asset_id'] ?? null;
        if (!\is_string($raw) || !Uuid::isValid($raw)) {
            return [new ValidationError('value.asset_id', 'asset.expected_uuid', 'Asset value must include a valid asset_id UUID.')];
        }

        return [];
    }
}
