<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Validator;

use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\AttributeGroupAttribute;
use App\Catalog\Domain\Entity\CategoryAttributeGroup;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\Entity\ObjectTypeAttribute;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;

/**
 * ADR-014 / MOD-04 (#896) — checks that an Attribute code is unique within
 * the **effective attribute model** of a target ObjectType.
 *
 * The effective model has two layers per ADR-014:
 *   1. **Base** — `object_type_attributes` junction. Attributes directly
 *      attached to the ObjectType.
 *   2. **Category-distributed** — `category_attribute_groups` whose
 *      `target_object_type_id` points at this ObjectType, transitively
 *      including every Attribute attached to each such AttributeGroup via
 *      `attribute_group_attributes`.
 *
 * The validator runs at attach-time (before adding an attribute to an OT's
 * base or to a group that distributes to that OT). It does NOT replace the
 * existing tenant-wide unique constraint on `attributes(tenant_id, code)`
 * — that constraint still guards against two attribute rows with the same
 * code in one tenant. This validator narrows the check to a single OT's
 * effective model so the operator sees a precise error message
 * (`existing_location` distinguishes "already in base" from "comes from
 * category X").
 *
 * Returns the {@see AttributeCodeConflict} on collision, `null` when the
 * code is free in the model. Callers translate the conflict into a
 * Problem Details 422 response.
 */
final readonly class AttributeCodeUniquenessValidator
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function validate(
        string $code,
        ObjectType $objectType,
        ?Attribute $excludeAttribute = null,
    ): ?AttributeCodeConflict {
        $excludeId = $excludeAttribute?->getId()->toRfc4122();

        // Layer 1 — directly attached via object_type_attributes.
        /** @var ObjectTypeAttribute|null $direct */
        $direct = $this->em
            ->createQuery(
                'SELECT j, a FROM '.ObjectTypeAttribute::class.' j'
                .' JOIN j.attribute a'
                .' WHERE j.objectType = :type AND a.code = :code'
                .(null !== $excludeId ? ' AND a.id <> :excludeId' : '')
            )
            ->setParameter('type', $objectType)
            ->setParameter('code', $code)
            ->setMaxResults(1)
            ->setParameters(
                null !== $excludeId
                    ? ['type' => $objectType, 'code' => $code, 'excludeId' => $excludeId]
                    : ['type' => $objectType, 'code' => $code],
            )
            ->getOneOrNullResult();

        if (null !== $direct) {
            return new AttributeCodeConflict(
                code: $code,
                existingLocation: 'base',
                conflictingAttributeId: $direct->getAttribute()->getId(),
            );
        }

        // Layer 2 — distributed via category_attribute_groups → attribute_group_attributes.
        /** @var array<int, array{a_id: string, category_id: string}> $rows */
        $rows = $this->em
            ->createQuery(
                'SELECT a.id AS a_id, cag.categoryObjectId AS category_id'
                .' FROM '.CategoryAttributeGroup::class.' cag'
                .' JOIN '.AttributeGroupAttribute::class.' aga'
                .' WITH aga.attributeGroup = cag.attributeGroup'
                .' JOIN aga.attribute a'
                .' WHERE cag.targetObjectType = :type AND a.code = :code'
                .(null !== $excludeId ? ' AND a.id <> :excludeId' : '')
            )
            ->setParameters(
                null !== $excludeId
                    ? ['type' => $objectType, 'code' => $code, 'excludeId' => $excludeId]
                    : ['type' => $objectType, 'code' => $code],
            )
            ->setMaxResults(1)
            ->getArrayResult();

        if ([] !== $rows) {
            $row = $rows[0];

            return new AttributeCodeConflict(
                code: $code,
                existingLocation: 'category:'.$this->toUuidString($row['category_id']),
                conflictingAttributeId: \Symfony\Component\Uid\Uuid::fromString($this->toUuidString($row['a_id'])),
            );
        }

        return null;
    }

    /**
     * `getArrayResult` returns Doctrine's `uuid` type as `Uuid` on local
     * hydration but as a string under CI's stricter PHPStan analysis.
     * Normalise both paths to RFC-4122 string.
     */
    private function toUuidString(mixed $raw): string
    {
        if ($raw instanceof \Symfony\Component\Uid\Uuid) {
            return $raw->toRfc4122();
        }
        if (\is_string($raw)) {
            return $raw;
        }
        throw new LogicException('Expected Uuid or string from Doctrine array result, got '.\get_debug_type($raw).'.');
    }
}
