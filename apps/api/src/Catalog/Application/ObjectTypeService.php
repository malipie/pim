<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\Entity\ObjectTypeAttribute;
use App\Catalog\Domain\Exception\BuiltInObjectTypeException;
use App\Catalog\Domain\Exception\DisabledFeatureException;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\ObjectTypeAttributeRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Application service that owns ObjectType lifecycle invariants.
 *
 * Two guards live here, deliberately at service-level rather than schema:
 *
 *   1. **Custom kinds gated by feature flag.** The
 *      `pim.catalog.enable_custom_object_types` parameter (default false in
 *      MVP) blocks creation of `kind=custom` ObjectTypes. Phase 2 unlocks
 *      it together with the agent tool `create_object_type`. Mitigation
 *      for risk R-29 (over-engineering: custom kinds without UX support).
 *
 *   2. **Built-in deletion blocked.** Predefined Product / Category /
 *      Asset rows (`is_built_in=true`) cannot be removed by service calls.
 *      Phase-2 RLS will enforce the same at the database level; until then
 *      this is the only barrier between an admin and accidental tenant
 *      catalog destruction.
 *
 * Junction lifecycle (`assignAttribute`, `unassignAttribute`) lives here
 * too so callers do not poke at `ObjectTypeAttribute` directly.
 */
final readonly class ObjectTypeService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ObjectTypeAttributeRepositoryInterface $junctions,
        private bool $enableCustomObjectTypes,
    ) {
    }

    /**
     * @param array<string, string> $label
     */
    public function create(
        string $code,
        ObjectKind $kind,
        array $label,
        bool $builtIn = false,
    ): ObjectType {
        if (ObjectKind::Custom === $kind && !$this->enableCustomObjectTypes) {
            throw DisabledFeatureException::customObjectTypesDisabled();
        }

        $objectType = new ObjectType($code, $kind, $label);
        if ($builtIn) {
            $objectType->markBuiltIn();
        }

        $this->em->persist($objectType);
        $this->em->flush();

        return $objectType;
    }

    public function delete(ObjectType $objectType): void
    {
        if ($objectType->isBuiltIn()) {
            throw BuiltInObjectTypeException::cannotDelete($objectType);
        }

        $this->em->remove($objectType);
        $this->em->flush();
    }

    /**
     * Idempotent attribute assignment: existing junction is updated in
     * place, missing one is created. Returns the live junction so callers
     * can read back the persisted defaults.
     */
    public function assignAttribute(
        ObjectType $objectType,
        Attribute $attribute,
        bool $required = false,
        int $sortOrder = 0,
    ): ObjectTypeAttribute {
        $junction = $this->junctions->findOne($objectType, $attribute);
        if (null === $junction) {
            $junction = new ObjectTypeAttribute($objectType, $attribute, $required, $sortOrder);
            $this->em->persist($junction);
        } else {
            $junction->changeRequiredForCompleteness($required);
            $junction->reorder($sortOrder);
        }

        $this->em->flush();

        return $junction;
    }

    public function unassignAttribute(ObjectType $objectType, Attribute $attribute): void
    {
        $junction = $this->junctions->findOne($objectType, $attribute);
        if (null === $junction) {
            return;
        }

        $this->em->remove($junction);
        $this->em->flush();
    }
}
