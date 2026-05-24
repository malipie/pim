<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Catalog\Application\BuiltInObjectTypeSeeder;
use App\Catalog\Application\BuiltInSystemAttributesSeeder;
use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\AttributeGroup;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\Entity\ObjectTypeAttributeGroup;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\AttributeGroupRepositoryInterface;
use App\Catalog\Domain\Repository\AttributeRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Integration coverage for UI-08.3 (#258) — system attributes + auto-attached
 * audit group. Exercises the {@see BuiltInSystemAttributesSeeder} + the
 * Doctrine listener that wires the audit group to future ObjectTypes.
 */
final class SystemAttributesAuditGroupTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $em = $this->em();
        $this->tenant = new Tenant('demo', 'Demo');
        $em->persist($this->tenant);
        $em->flush();
        $this->tenantContext()->set($this->tenant);

        // Built-in ObjectTypes are seeded *before* the audit group exists,
        // mirroring the AppFixtures + onboarding order.
        self::getContainer()->get(BuiltInObjectTypeSeeder::class)->seed($this->tenant);
    }

    #[Test]
    public function seederMintsSystemAttributesAndAuditGroup(): void
    {
        self::getContainer()->get(BuiltInSystemAttributesSeeder::class)->seed($this->tenant);

        $auditGroup = $this->attributeGroupRepository()->findByCode('audit', $this->tenant);
        self::assertInstanceOf(AttributeGroup::class, $auditGroup);
        self::assertTrue($auditGroup->isSystemGroup());
        self::assertTrue($auditGroup->isAutoAttached());

        $attrs = $this->attributeRepository();
        foreach (['created_at', 'updated_at', 'created_by', 'updated_by'] as $code) {
            $attribute = $attrs->findByCode($code, $this->tenant);
            self::assertInstanceOf(Attribute::class, $attribute, "Missing system attribute: {$code}");
            self::assertTrue($attribute->isSystem(), "Attribute {$code} should be is_system=true");
        }

        $createdAt = $attrs->findByCode('created_at', $this->tenant);
        self::assertNotNull($createdAt);
        self::assertSame(AttributeType::Datetime, $createdAt->getType());

        $createdBy = $attrs->findByCode('created_by', $this->tenant);
        self::assertNotNull($createdBy);
        self::assertSame(AttributeType::Reference, $createdBy->getType());
        self::assertSame(['target_entity' => 'user'], $createdBy->getValidationRules());
    }

    #[Test]
    public function auditGroupIsAttachedToEveryBuiltInObjectTypeAfterSeeding(): void
    {
        self::getContainer()->get(BuiltInSystemAttributesSeeder::class)->seed($this->tenant);

        $em = $this->em();
        $em->clear();

        $auditGroup = $this->attributeGroupRepository()->findByCode('audit', $this->tenant);
        self::assertNotNull($auditGroup);

        $junctions = $em
            ->createQuery(
                'SELECT j FROM '.ObjectTypeAttributeGroup::class.' j WHERE j.attributeGroup = :g'
            )
            ->setParameter('g', $auditGroup)
            ->getResult();

        // ADR-014 / MOD-10 (#902): Brand removed from built-in pool — three
        // built-ins remain (product/category/asset), each gets the audit
        // group auto-attached.
        self::assertCount(3, $junctions, 'Audit group must be attached to all 3 built-in ObjectTypes (product/category/asset).');
        foreach ($junctions as $junction) {
            self::assertInstanceOf(ObjectTypeAttributeGroup::class, $junction);
            self::assertSame(999, $junction->getPosition());
        }
    }

    #[Test]
    public function listenerAutoAttachesAuditGroupToObjectTypesPersistedAfterSeeding(): void
    {
        self::getContainer()->get(BuiltInSystemAttributesSeeder::class)->seed($this->tenant);

        $em = $this->em();

        // A fresh ObjectType persisted *after* the audit group exists must
        // pick up the junction via AutoAttachAuditGroupListener::postPersist.
        $service = new ObjectType('service', ObjectKind::Custom, ['en' => 'Service', 'pl' => 'Usługa']);
        $em->persist($service);
        $em->flush();
        $em->clear();

        $auditGroup = $this->attributeGroupRepository()->findByCode('audit', $this->tenant);
        self::assertNotNull($auditGroup);

        $reloaded = $this->objectTypeRepository()->findByCode('service', $this->tenant);
        self::assertNotNull($reloaded);

        $junction = $em
            ->createQuery(
                'SELECT j FROM '.ObjectTypeAttributeGroup::class.' j'
                .' WHERE j.objectType = :ot AND j.attributeGroup = :g'
            )
            ->setParameter('ot', $reloaded)
            ->setParameter('g', $auditGroup)
            ->getOneOrNullResult();

        self::assertInstanceOf(ObjectTypeAttributeGroup::class, $junction);
        self::assertSame(999, $junction->getPosition());
    }

    #[Test]
    public function secondSeederInvocationIsIdempotent(): void
    {
        $seeder = self::getContainer()->get(BuiltInSystemAttributesSeeder::class);
        $seeder->seed($this->tenant);
        $beforeAttrs = $this->systemAttributeCount();
        $beforeJunctions = $this->auditJunctionCount();

        $seeder->seed($this->tenant);

        self::assertSame($beforeAttrs, $this->systemAttributeCount());
        self::assertSame($beforeJunctions, $this->auditJunctionCount());
    }

    private function systemAttributeCount(): int
    {
        return (int) $this->em()
            ->createQuery('SELECT COUNT(a) FROM '.Attribute::class.' a WHERE a.isSystem = true')
            ->getSingleScalarResult();
    }

    private function auditJunctionCount(): int
    {
        $count = $this->em()->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM object_type_attribute_groups oag'
            .' JOIN attribute_groups g ON g.id = oag.attribute_group_id'
            .' WHERE g.code = ?',
            ['audit'],
        );

        return (int) (\is_scalar($count) ? $count : 0);
    }

    private function attributeRepository(): AttributeRepositoryInterface
    {
        $repo = self::getContainer()->get(AttributeRepositoryInterface::class);

        return $repo;
    }

    private function attributeGroupRepository(): AttributeGroupRepositoryInterface
    {
        $repo = self::getContainer()->get(AttributeGroupRepositoryInterface::class);

        return $repo;
    }

    private function objectTypeRepository(): ObjectTypeRepositoryInterface
    {
        $repo = self::getContainer()->get(ObjectTypeRepositoryInterface::class);

        return $repo;
    }

    private function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }

    private function tenantContext(): TenantContext
    {
        return self::getContainer()->get(TenantContext::class);
    }
}
