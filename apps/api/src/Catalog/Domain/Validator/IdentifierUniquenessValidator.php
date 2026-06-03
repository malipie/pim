<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Validator;

use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\CatalogObject;
use Doctrine\DBAL\Connection;

/**
 * #1179 — application-level pre-check for `identifier` value uniqueness
 * within one ObjectType, so the API returns a clean 409 before hitting
 * the DB-level partial unique index (the index remains the race-proof
 * source of truth).
 *
 * Queries the trigger-maintained denormalised columns directly so the
 * lookup rides the `object_values_identifier_uniq` index. Existing
 * identifier rows always have the columns populated (the trigger runs on
 * every write); the object being saved is excluded by id so re-saving the
 * same value does not collide with itself.
 *
 * Returns `true` when `$value` is already used by another object of the
 * same ObjectType for the same attribute; `false` when it is free.
 */
final readonly class IdentifierUniquenessValidator
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function isDuplicate(CatalogObject $object, Attribute $attribute, string $value): bool
    {
        $tenant = $object->getTenant();
        if (null === $tenant) {
            return false;
        }

        $found = $this->connection->fetchOne(
            'SELECT 1 FROM object_values'
            .' WHERE tenant_id = :tenant'
            .' AND identifier_object_type_id = :objectType'
            .' AND attribute_id = :attribute'
            .' AND identifier_value = :value'
            .' AND object_id <> :currentObject'
            .' LIMIT 1',
            [
                'tenant' => $tenant->getId()->toRfc4122(),
                'objectType' => $object->getObjectType()->getId()->toRfc4122(),
                'attribute' => $attribute->getId()->toRfc4122(),
                'value' => $value,
                'currentObject' => $object->getId()->toRfc4122(),
            ],
        );

        return false !== $found;
    }
}
