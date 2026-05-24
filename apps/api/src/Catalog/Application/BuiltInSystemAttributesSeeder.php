<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\AttributeGroup;
use App\Catalog\Domain\Entity\AttributeGroupAttribute;
use App\Catalog\Domain\Entity\ObjectTypeAttributeGroup;
use App\Catalog\Domain\Repository\AttributeGroupRepositoryInterface;
use App\Catalog\Domain\Repository\AttributeRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;

/**
 * UI-08.3 (#258) — runtime per-tenant seeder for the system attributes
 * (`created_at`, `updated_at`, `created_by`, `updated_by`) and the
 * auto-attached audit AttributeGroup.
 *
 * Mirrors {@see BuiltInObjectTypeSeeder} — the migration handles every
 * tenant that exists at deploy time; this service handles tenants created
 * after that. Idempotent: re-runs are a no-op once the rows exist.
 *
 * Order matters: ObjectTypes are seeded *before* this seeder runs because
 * {@see \App\Catalog\Infrastructure\Doctrine\EventListener\AutoAttachAuditGroupListener}
 * needs the audit group to already be findable via `findByCode('audit')`.
 * Call sequence in fixtures + tenant onboarding:
 *
 *   1. {@see BuiltInObjectTypeSeeder::seed}
 *   2. {@see BuiltInSystemAttributesSeeder::seed}
 *   3. (any custom ObjectType seed — listener auto-attaches audit group)
 */
final readonly class BuiltInSystemAttributesSeeder
{
    /**
     * @var array<string, array{AttributeType, array<string, string>, array<string, mixed>, int}>
     */
    private const array ATTRIBUTES = [
        'created_at' => [
            AttributeType::Datetime,
            ['pl' => 'Utworzono', 'en' => 'Created at'],
            [],
            1,
        ],
        'updated_at' => [
            AttributeType::Datetime,
            ['pl' => 'Zmieniono', 'en' => 'Updated at'],
            [],
            2,
        ],
        'created_by' => [
            AttributeType::Reference,
            ['pl' => 'Utworzony przez', 'en' => 'Created by'],
            ['target_entity' => 'user'],
            3,
        ],
        'updated_by' => [
            AttributeType::Reference,
            ['pl' => 'Zmieniony przez', 'en' => 'Updated by'],
            ['target_entity' => 'user'],
            4,
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
     * Seed missing system attributes + audit AttributeGroup for the given
     * tenant. Returns the number of attributes actually created (the audit
     * group is counted separately — non-zero return implies the group was
     * also seeded if it was missing).
     */
    public function seed(Tenant $tenant): int
    {
        $previous = $this->tenantContext->get();
        $this->tenantContext->set($tenant);

        try {
            $auditGroup = $this->attributeGroupRepository->findByCode('audit', $tenant);
            if (null === $auditGroup) {
                $auditGroup = new AttributeGroup(
                    code: 'audit',
                    label: ['pl' => 'Audyt', 'en' => 'Audit'],
                    position: 999,
                    description: [
                        'pl' => 'Atrybuty systemowe — kto, kiedy.',
                        'en' => 'System attributes — who and when.',
                    ],
                    icon: 'ShieldCheck',
                    color: '#64748B',
                    isSystemGroup: true,
                    autoAttached: true,
                    // VIEW-03 (#375) behavior flags: audit is required + non-shared
                    // (each ObjectType gets its own auto-attached copy) + non-conditional.
                    isRequiredSection: true,
                    isShared: false,
                    hasConditionalVisibility: false,
                );
                $this->em->persist($auditGroup);
                $this->em->flush();
            }

            $created = 0;
            foreach (self::ATTRIBUTES as $code => [$type, $label, $rules, $position]) {
                $existing = $this->attributeRepository->findByCode($code, $tenant);
                if (null !== $existing) {
                    continue;
                }

                $attribute = new Attribute($code, $label, $type);
                $attribute->markSystem();
                $attribute->reorder($position);
                if ([] !== $rules) {
                    $attribute->updateValidationRules($rules);
                }
                $this->em->persist($attribute);
                $this->em->flush();

                $junction = new AttributeGroupAttribute(
                    attributeGroup: $auditGroup,
                    attribute: $attribute,
                    position: $position,
                );
                $this->em->persist($junction);
                $this->em->flush();
                ++$created;
            }

            // Auto-attach the audit group to every existing ObjectType in
            // this tenant. The Doctrine listener handles future ObjectTypes
            // (custom kinds, new built-ins added later) — this loop covers
            // the common case where ObjectTypes were persisted *before* the
            // audit group existed (fixture flow + tenant onboarding).
            $objectTypes = $this->objectTypeRepository->findAllByTenant($tenant);
            $existingJunctions = $this->em
                ->createQuery(
                    'SELECT IDENTITY(j.objectType) AS object_type_id'
                    .' FROM '.ObjectTypeAttributeGroup::class.' j'
                    .' WHERE j.attributeGroup = :group'
                )
                ->setParameter('group', $auditGroup)
                ->getArrayResult();
            $alreadyAttached = array_column($existingJunctions, 'object_type_id');

            foreach ($objectTypes as $objectType) {
                if (\in_array($objectType->getId()->toRfc4122(), $alreadyAttached, true)) {
                    continue;
                }
                $this->em->persist(new ObjectTypeAttributeGroup(
                    $objectType,
                    $auditGroup,
                    position: 999,
                    displayMode: ObjectTypeAttributeGroup::DISPLAY_MODE_STACKED,
                ));
            }
            $this->em->flush();

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
