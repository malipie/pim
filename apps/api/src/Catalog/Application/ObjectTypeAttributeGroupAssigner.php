<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Domain\Entity\AttributeGroup;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\Entity\ObjectTypeAttributeGroup;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Idempotent attachment of an AttributeGroup to an ObjectType — the mirror of
 * {@see ObjectTypeService::assignAttribute} for groups.
 *
 * The existing junction is left untouched; a missing one is created. A cheap
 * DBAL existence check avoids hydrating the composite-key junction entity.
 * Shared by the attach controller and the structural import creator so the
 * "attach group to module" semantics live in one place.
 */
final readonly class ObjectTypeAttributeGroupAssigner
{
    public function __construct(
        private EntityManagerInterface $em,
        private Connection $connection,
    ) {
    }

    public function assign(ObjectType $objectType, AttributeGroup $group): void
    {
        $existing = $this->connection->fetchOne(
            'SELECT 1 FROM object_type_attribute_groups WHERE object_type_id = ? AND attribute_group_id = ?',
            [$objectType->getId()->toRfc4122(), $group->getId()->toRfc4122()],
        );
        if (false === $existing) {
            $this->em->persist(new ObjectTypeAttributeGroup($objectType, $group));
            $this->em->flush();
        }
    }
}
