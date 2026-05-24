<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\AttributeGroup;
use App\Catalog\Domain\Entity\AttributeGroupAttribute;
use App\Catalog\Domain\Entity\ObjectTypeAttribute;
use App\Catalog\Domain\Entity\ObjectTypeAttributeGroup;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\RelationCardinality;
use App\Catalog\Domain\Repository\AttributeGroupRepositoryInterface;
use App\Catalog\Domain\Repository\AttributeRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;

/**
 * ADR-014 / MOD-02 (#894) — per-tenant seeder for the five built-in
 * `relation`-typed Attributes attached to the Product ObjectType
 * (`cross_sell`, `up_sell`, `related`, `alternative`, `accessory`).
 *
 * Replaces the legacy {@see BuiltInAssociationTypeSeeder} that seeded
 * four `association_types` rows. The new seeder is wider: each "kind of
 * link" is a first-class Attribute carrying full MOD-01 config
 * (`relation_target_object_type_ids`, `relation_cardinality`,
 * `relation_advanced`) and lives in an AttributeGroup ("Powiązania") so
 * the modeling UI surfaces them as a regular tab on the product form.
 *
 * Run order in the fixtures / onboarding pipeline:
 *   1. {@see BuiltInObjectTypeSeeder::seed}
 *   2. {@see BuiltInSystemAttributesSeeder::seed}
 *   3. {@see self::seed}            ← here
 *
 * Idempotent: each lookup is keyed by `(tenant, code)`; re-runs after the
 * 6 rows exist (1 group + 5 attributes) are no-ops. Attribute → group +
 * Attribute → ObjectType junctions are also re-checked before insert.
 */
final readonly class BuiltInProductRelationAttributesSeeder
{
    /** Position of the "Powiązania" AttributeGroup in the form layout. */
    private const int GROUP_POSITION = 500;

    private const string GROUP_CODE = 'relations';

    /**
     * @var array<string, array{label: array<string, string>, position: int, advanced: bool}>
     */
    private const array DEFINITIONS = [
        'cross_sell' => [
            'label' => ['pl' => 'Sprzedaż krzyżowa', 'en' => 'Cross-sell'],
            'position' => 10,
            'advanced' => false,
        ],
        'up_sell' => [
            'label' => ['pl' => 'Sprzedaż dodatkowa', 'en' => 'Up-sell'],
            'position' => 20,
            'advanced' => false,
        ],
        'related' => [
            'label' => ['pl' => 'Powiązane', 'en' => 'Related'],
            'position' => 30,
            'advanced' => false,
        ],
        'alternative' => [
            'label' => ['pl' => 'Alternatywne', 'en' => 'Alternative'],
            'position' => 40,
            'advanced' => false,
        ],
        'accessory' => [
            'label' => ['pl' => 'Akcesoria', 'en' => 'Accessory'],
            'position' => 50,
            'advanced' => false,
        ],
    ];

    public function __construct(
        private AttributeRepositoryInterface $attributeRepository,
        private AttributeGroupRepositoryInterface $attributeGroupRepository,
        private ObjectTypeRepositoryInterface $objectTypeRepository,
        private EntityManagerInterface $em,
        private TenantContext $tenantContext,
    ) {
    }

    /**
     * Seed missing built-in relation attributes for the given tenant.
     * Returns the number of attribute rows actually created.
     */
    public function seed(Tenant $tenant): int
    {
        $previous = $this->tenantContext->get();
        $this->tenantContext->set($tenant);

        try {
            $productType = $this->objectTypeRepository->findBuiltInByKind(ObjectKind::Product, $tenant);
            if (null === $productType) {
                // BuiltInObjectTypeSeeder must run first — if Product is
                // missing the rest of the seed has no anchor to attach to.
                return 0;
            }

            // Step 1 — ensure the "Powiązania" AttributeGroup exists and is
            // wired to the Product ObjectType.
            $group = $this->attributeGroupRepository->findByCode(self::GROUP_CODE, $tenant);
            if (null === $group) {
                $group = new AttributeGroup(
                    code: self::GROUP_CODE,
                    label: ['pl' => 'Powiązania', 'en' => 'Relations'],
                    position: self::GROUP_POSITION,
                    description: [
                        'pl' => 'Wbudowane atrybuty łączące produkt z innymi produktami (ADR-014).',
                        'en' => 'Built-in attributes linking the product to other products (ADR-014).',
                    ],
                    icon: 'Link2',
                    color: '#0EA5E9',
                    isSystemGroup: true,
                    autoAttached: false,
                    isRequiredSection: false,
                    isShared: false,
                    hasConditionalVisibility: false,
                );
                $this->em->persist($group);
                $this->em->flush();
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

            // Step 2 — seed each missing relation attribute, wire to the
            // group + ObjectType junctions.
            $created = 0;
            $productTargetIds = [$productType->getId()->toRfc4122()];
            foreach (self::DEFINITIONS as $code => $definition) {
                $existing = $this->attributeRepository->findByCode($code, $tenant);
                if (null !== $existing) {
                    continue;
                }

                $attribute = new Attribute($code, $definition['label'], AttributeType::Relation);
                $attribute->markSystem();
                $attribute->reorder($definition['position']);
                $attribute->setRelationTargetObjectTypeIds($productTargetIds);
                $attribute->setRelationCardinality(RelationCardinality::Many);
                $attribute->setRelationAdvanced($definition['advanced']);
                $this->em->persist($attribute);
                $this->em->flush();

                $this->em->persist(new AttributeGroupAttribute(
                    attributeGroup: $group,
                    attribute: $attribute,
                    position: $definition['position'],
                ));
                $this->em->persist(new ObjectTypeAttribute(
                    objectType: $productType,
                    attribute: $attribute,
                    requiredForCompleteness: false,
                    sortOrder: self::GROUP_POSITION + $definition['position'],
                ));
                $this->em->flush();
                ++$created;
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
