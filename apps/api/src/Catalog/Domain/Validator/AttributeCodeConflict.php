<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Validator;

use Symfony\Component\Uid\Uuid;

/**
 * ADR-014 / MOD-04 (#896) — value object describing where an Attribute code
 * already exists in the target ObjectType's effective model.
 *
 * Returned by {@see AttributeCodeUniquenessValidator::validate} when a
 * collision is detected. The controller layer projects the fields into an
 * RFC 7807 Problem Details response so the FE can render
 * "Already used in: base layer / category Telewizory".
 *
 * `existingLocation` is a stable machine-readable token:
 *   - `'base'`           — directly attached to the ObjectType via
 *                          `object_type_attributes`.
 *   - `'category:<code>'` — distributed to the ObjectType via a
 *                          `category_attribute_groups` row whose source
 *                          category has the given `code`.
 */
final readonly class AttributeCodeConflict
{
    public function __construct(
        public string $code,
        public string $existingLocation,
        public Uuid $conflictingAttributeId,
    ) {
    }
}
