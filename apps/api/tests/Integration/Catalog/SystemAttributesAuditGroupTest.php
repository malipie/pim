<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Catalog\Application\BuiltInObjectTypeSeeder;
use App\Catalog\Application\BuiltInSystemAttributesSeeder;
use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\ObjectType;
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
 * Integration coverage for system audit attributes. Audit metadata is seeded
 * as attributes only; display grouping is explicit modeling configuration.
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

        self::getContainer()->get(BuiltInObjectTypeSeeder::class)->seed($this->tenant);
    }

    #[Test]
    public function seederMintsSystemAttributesWithoutAuditGroup(): void
    {
        self::getContainer()->get(BuiltInSystemAttributesSeeder::class)->seed($this->tenant);

        $auditGroup = $this->attributeGroupRepository()->findByCode('audit', $this->tenant);
        self::assertNull($auditGroup, 'Audit AttributeGroup is optional and must not be seeded.');

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
    public function seedingDoesNotAttachAuditGroupToBuiltInObjectTypes(): void
    {
        self::getContainer()->get(BuiltInSystemAttributesSeeder::class)->seed($this->tenant);

        self::assertSame(0, $this->auditJunctionCount());
    }

    #[Test]
    public function laterObjectTypesAreNotAutoAttachedToAuditGroup(): void
    {
        self::getContainer()->get(BuiltInSystemAttributesSeeder::class)->seed($this->tenant);

        $em = $this->em();

        $service = new ObjectType('service', ObjectKind::Custom, ['en' => 'Service', 'pl' => 'Usługa']);
        $em->persist($service);
        $em->flush();
        $em->clear();

        $auditGroup = $this->attributeGroupRepository()->findByCode('audit', $this->tenant);
        self::assertNull($auditGroup);

        $reloaded = $this->objectTypeRepository()->findByCode('service', $this->tenant);
        self::assertNotNull($reloaded);
        self::assertSame(0, $this->auditJunctionCount());
    }

    #[Test]
    public function secondSeederInvocationIsIdempotent(): void
    {
        $seeder = self::getContainer()->get(BuiltInSystemAttributesSeeder::class);
        $seeder->seed($this->tenant);
        $beforeAttrs = $this->systemAttributeCount();

        $seeder->seed($this->tenant);

        self::assertSame($beforeAttrs, $this->systemAttributeCount());
        self::assertSame(0, $this->auditJunctionCount());
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
