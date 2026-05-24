<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Catalog\Application\BuiltInObjectTypeSeeder;
use App\Catalog\Application\BuiltInProductRelationAttributesSeeder;
use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\RelationCardinality;
use App\Catalog\Domain\Repository\AttributeGroupRepositoryInterface;
use App\Catalog\Domain\Repository\AttributeRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class BuiltInProductRelationAttributesSeederTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private const array EXPECTED_CODES = [
        'cross_sell',
        'up_sell',
        'related',
        'alternative',
        'accessory',
    ];

    #[Test]
    public function seedCreatesFiveRelationAttributesOnProductObjectType(): void
    {
        $tenant = $this->createTenant('demo');
        $this->builtInObjectTypeSeeder()->seed($tenant);

        $created = $this->seeder()->seed($tenant);

        self::assertSame(5, $created);

        $productType = $this->objectTypeRepository()->findBuiltInByKind(ObjectKind::Product, $tenant);
        self::assertNotNull($productType);
        $productTypeId = $productType->getId()->toRfc4122();

        foreach (self::EXPECTED_CODES as $code) {
            $attribute = $this->attributeRepository()->findByCode($code, $tenant);
            self::assertNotNull($attribute, "missing attribute {$code}");
            self::assertSame(AttributeType::Relation, $attribute->getType(), "{$code} type");
            self::assertSame(RelationCardinality::Many, $attribute->getRelationCardinality(), "{$code} cardinality");
            self::assertFalse($attribute->isRelationAdvanced(), "{$code} advanced");
            self::assertSame([$productTypeId], $attribute->getRelationTargetObjectTypeIds(), "{$code} target");
            self::assertTrue($attribute->isSystem(), "{$code} system flag");
        }
    }

    #[Test]
    public function seedAttachesPowiazaniaGroupToProductObjectType(): void
    {
        $tenant = $this->createTenant('demo');
        $this->builtInObjectTypeSeeder()->seed($tenant);

        $this->seeder()->seed($tenant);

        $group = $this->attributeGroupRepository()->findByCode('relations', $tenant);
        self::assertNotNull($group, 'relations AttributeGroup should be seeded');
        self::assertSame('relations', $group->getCode());
        self::assertSame(['pl' => 'Powiązania', 'en' => 'Relations'], $group->getLabel());
        self::assertTrue($group->isSystemGroup());
    }

    #[Test]
    public function seedIsIdempotent(): void
    {
        $tenant = $this->createTenant('demo');
        $this->builtInObjectTypeSeeder()->seed($tenant);
        $seeder = $this->seeder();

        self::assertSame(5, $seeder->seed($tenant), 'first run creates the five attrs');
        self::assertSame(0, $seeder->seed($tenant), 'second run is a no-op');
    }

    #[Test]
    public function seedSkipsTenantsWithoutBuiltInProduct(): void
    {
        $tenant = $this->createTenant('demo');
        // Note: BuiltInObjectTypeSeeder NOT run on purpose — Product is missing.

        $created = $this->seeder()->seed($tenant);

        self::assertSame(0, $created, 'no anchor → no rows');
    }

    private function seeder(): BuiltInProductRelationAttributesSeeder
    {
        return self::getContainer()->get(BuiltInProductRelationAttributesSeeder::class);
    }

    private function builtInObjectTypeSeeder(): BuiltInObjectTypeSeeder
    {
        return self::getContainer()->get(BuiltInObjectTypeSeeder::class);
    }

    private function attributeRepository(): AttributeRepositoryInterface
    {
        return self::getContainer()->get(AttributeRepositoryInterface::class);
    }

    private function attributeGroupRepository(): AttributeGroupRepositoryInterface
    {
        return self::getContainer()->get(AttributeGroupRepositoryInterface::class);
    }

    private function objectTypeRepository(): ObjectTypeRepositoryInterface
    {
        return self::getContainer()->get(ObjectTypeRepositoryInterface::class);
    }

    private function createTenant(string $code): Tenant
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);
        $tenant = new Tenant($code, ucfirst($code).' Tenant');
        $em->persist($tenant);
        $em->flush();

        return $tenant;
    }
}
