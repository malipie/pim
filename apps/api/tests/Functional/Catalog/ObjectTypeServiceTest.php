<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog;

use App\Catalog\Application\ObjectTypeService;
use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\Exception\BuiltInObjectTypeException;
use App\Catalog\Domain\Exception\DisabledFeatureException;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Infrastructure\Doctrine\Repository\ObjectTypeAttributeRepository;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class ObjectTypeServiceTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private Tenant $tenant;
    private Attribute $someAttribute;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $em = $this->em();
        $tenant = new Tenant('demo', 'Demo Tenant');
        $em->persist($tenant);
        $em->flush();

        $this->tenant = $tenant;
        $this->tenantContext()->set($tenant);

        $attribute = new Attribute('name', ['pl' => 'Nazwa', 'en' => 'Name'], AttributeType::Text);
        $em->persist($attribute);
        $em->flush();

        $this->someAttribute = $attribute;
    }

    #[Test]
    public function createBuiltInProductTypeWorks(): void
    {
        $type = $this->serviceWithCustomEnabled(false)->create(
            'product',
            ObjectKind::Product,
            ['pl' => 'Produkt', 'en' => 'Product'],
            builtIn: true,
        );

        self::assertSame('product', $type->getCode());
        self::assertSame(ObjectKind::Product, $type->getKind());
        self::assertTrue($type->isBuiltIn());
        self::assertSame($this->tenant, $type->getTenant());
    }

    #[Test]
    public function createCustomTypeIsBlockedWhenFeatureFlagDisabled(): void
    {
        $service = $this->serviceWithCustomEnabled(false);

        $this->expectException(DisabledFeatureException::class);
        $service->create('shoes', ObjectKind::Custom, ['pl' => 'Buty', 'en' => 'Shoes']);
    }

    #[Test]
    public function createCustomTypeWorksWhenFeatureFlagEnabled(): void
    {
        $type = $this->serviceWithCustomEnabled(true)->create(
            'shoes',
            ObjectKind::Custom,
            ['pl' => 'Buty', 'en' => 'Shoes'],
        );

        self::assertSame(ObjectKind::Custom, $type->getKind());
        self::assertFalse($type->isBuiltIn());
    }

    #[Test]
    public function deleteBuiltInTypeIsBlocked(): void
    {
        $type = $this->serviceWithCustomEnabled(false)->create(
            'product',
            ObjectKind::Product,
            ['pl' => 'Produkt'],
            builtIn: true,
        );

        $this->expectException(BuiltInObjectTypeException::class);
        $this->serviceWithCustomEnabled(false)->delete($type);
    }

    #[Test]
    public function deleteNonBuiltInTypeWorks(): void
    {
        $service = $this->serviceWithCustomEnabled(true);
        $type = $service->create('shoes', ObjectKind::Custom, ['pl' => 'Buty']);
        $id = $type->getId();

        $service->delete($type);
        $this->em()->clear();

        self::assertNull($this->em()->find(ObjectType::class, $id));
    }

    #[Test]
    public function assignAttributeCreatesJunction(): void
    {
        $service = $this->serviceWithCustomEnabled(false);
        $type = $service->create('product', ObjectKind::Product, ['pl' => 'Produkt'], builtIn: true);

        $junction = $service->assignAttribute($type, $this->someAttribute, required: true, sortOrder: 5);

        self::assertSame($type, $junction->getObjectType());
        self::assertSame($this->someAttribute, $junction->getAttribute());
        self::assertTrue($junction->isRequiredForCompleteness());
        self::assertSame(5, $junction->getSortOrder());
    }

    #[Test]
    public function assignAttributeIsIdempotentAndUpdatesExistingJunction(): void
    {
        $service = $this->serviceWithCustomEnabled(false);
        $type = $service->create('product', ObjectKind::Product, ['pl' => 'Produkt'], builtIn: true);

        $service->assignAttribute($type, $this->someAttribute, required: false, sortOrder: 0);
        $service->assignAttribute($type, $this->someAttribute, required: true, sortOrder: 10);

        $junctions = self::getContainer()
            ->get(ObjectTypeAttributeRepository::class)
            ->findByObjectType($type);
        self::assertCount(1, $junctions);
        self::assertTrue($junctions[0]->isRequiredForCompleteness());
        self::assertSame(10, $junctions[0]->getSortOrder());
    }

    #[Test]
    public function unassignAttributeRemovesJunction(): void
    {
        $service = $this->serviceWithCustomEnabled(false);
        $type = $service->create('product', ObjectKind::Product, ['pl' => 'Produkt'], builtIn: true);
        $service->assignAttribute($type, $this->someAttribute);

        $service->unassignAttribute($type, $this->someAttribute);

        $junctions = self::getContainer()
            ->get(ObjectTypeAttributeRepository::class)
            ->findByObjectType($type);
        self::assertSame([], $junctions);
    }

    private function serviceWithCustomEnabled(bool $flag): ObjectTypeService
    {
        return new ObjectTypeService(
            $this->em(),
            self::getContainer()->get(ObjectTypeAttributeRepository::class),
            $flag,
        );
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
