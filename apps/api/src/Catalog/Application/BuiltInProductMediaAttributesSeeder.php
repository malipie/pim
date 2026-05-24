<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Domain\Entity\AttributeGroup;
use App\Catalog\Domain\Entity\ObjectTypeAttributeGroup;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\AttributeGroupRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;

/**
 * ADR-014 / MODR-02 (#924) — per-tenant seeder for the built-in
 * "Multimedia" AttributeGroup attached to the Product ObjectType.
 *
 * Counterpart to {@see BuiltInProductRelationAttributesSeeder} for the
 * media side. The group is seeded empty in MVP — `asset`-typed attributes
 * hosted in it are added through Modelowanie when the operator wires
 * specific media slots (or a follow-up ticket migrates the legacy
 * `product_assets` m2m into a first-class `asset` attribute).
 *
 * The whole point of MODR-02 is to *stop hardcoding* the Multimedia tab:
 * once the group exists with `is_system_group=true` (so it cannot be
 * detached) and `display_mode='tab'` (inherited via the MODR-01 column
 * default), the form-schema renderer (MODR-03) can derive the tab from
 * `effectiveGroups` like every other group.
 *
 * Idempotent: re-runs after the row exists are no-ops.
 */
final readonly class BuiltInProductMediaAttributesSeeder
{
    /** Position of the "Multimedia" AttributeGroup in the form layout. */
    private const int GROUP_POSITION = 400;

    private const string GROUP_CODE = 'media';

    public function __construct(
        private AttributeGroupRepositoryInterface $attributeGroupRepository,
        private ObjectTypeRepositoryInterface $objectTypeRepository,
        private EntityManagerInterface $em,
        private TenantContext $tenantContext,
    ) {
    }

    /**
     * Seed the missing built-in media group for the given tenant. Returns
     * 1 if a new group was created, 0 if it already existed.
     */
    public function seed(Tenant $tenant): int
    {
        $previous = $this->tenantContext->get();
        $this->tenantContext->set($tenant);

        try {
            $productType = $this->objectTypeRepository->findBuiltInByKind(ObjectKind::Product, $tenant);
            if (null === $productType) {
                return 0;
            }

            $created = 0;
            $group = $this->attributeGroupRepository->findByCode(self::GROUP_CODE, $tenant);
            if (null === $group) {
                $group = new AttributeGroup(
                    code: self::GROUP_CODE,
                    label: ['pl' => 'Multimedia', 'en' => 'Media'],
                    position: self::GROUP_POSITION,
                    description: [
                        'pl' => 'Wbudowana grupa na pliki produktu — zdjęcia, dokumenty, multimedia (ADR-014).',
                        'en' => 'Built-in group for product media — images, documents, multimedia (ADR-014).',
                    ],
                    icon: 'Image',
                    color: '#8B5CF6',
                    isSystemGroup: true,
                    autoAttached: false,
                    isRequiredSection: false,
                    isShared: false,
                    hasConditionalVisibility: false,
                );
                $this->em->persist($group);
                $this->em->flush();
                $created = 1;
            }

            $groupAttachedToProduct = $this->em
                ->createQuery(
                    'SELECT 1 FROM '.ObjectTypeAttributeGroup::class.' j'
                    .' WHERE j.objectType = :objectType AND j.attributeGroup = :group'
                )
                ->setParameter('objectType', $productType)
                ->setParameter('group', $group)
                ->setMaxResults(1)
                ->getOneOrNullResult();
            if (null === $groupAttachedToProduct) {
                $this->em->persist(new ObjectTypeAttributeGroup($productType, $group, position: self::GROUP_POSITION));
                $this->em->flush();
            }

            return $created;
        } finally {
            if (null === $previous) {
                $this->tenantContext->clear();
            } else {
                $this->tenantContext->set($previous);
            }
        }
    }
}
