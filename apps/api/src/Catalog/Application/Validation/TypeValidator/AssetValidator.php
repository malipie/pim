<?php

declare(strict_types=1);

namespace App\Catalog\Application\Validation\TypeValidator;

use App\Catalog\Application\Validation\AttributeValueValidatorInterface;
use App\Catalog\Application\Validation\ValidationError;
use App\Catalog\Domain\Entity\Attribute;
use Symfony\Component\Uid\Uuid;

/**
 * `asset` AttributeType validator — value points at one Asset row or, for a
 * gallery, a list of them.
 *
 * Schema-shape only here: `value.asset_id` is either a single UUID string
 * ({@see \App\Import\Application\Service\ImportObjectCreator} keeps the scalar
 * shape for a lone asset) or a non-empty list of UUID strings (the gallery
 * shape written by the media-download path). Cross-checking that each row
 * actually exists + belongs to the same tenant is the API/import layer's job
 * where the database is reachable.
 */
final class AssetValidator implements AttributeValueValidatorInterface
{
    public function validate(Attribute $attribute, array $value): array
    {
        $raw = $value['asset_id'] ?? null;

        if (\is_array($raw)) {
            if ([] === $raw) {
                return [new ValidationError('value.asset_id', 'asset.empty_list', 'Asset gallery must contain at least one asset_id UUID.')];
            }
            $errors = [];
            foreach (array_values($raw) as $i => $id) {
                if (!\is_string($id) || !Uuid::isValid($id)) {
                    $errors[] = new ValidationError(\sprintf('value.asset_id.%d', $i), 'asset.expected_uuid', 'Each gallery asset_id must be a valid UUID.');
                }
            }

            return $errors;
        }

        if (!\is_string($raw) || !Uuid::isValid($raw)) {
            return [new ValidationError('value.asset_id', 'asset.expected_uuid', 'Asset value must include a valid asset_id UUID.')];
        }

        return [];
    }
}
