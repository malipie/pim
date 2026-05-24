<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Catalog\Application\BuiltInObjectTypeSeeder;
use App\Catalog\Application\BuiltInProductMediaAttributesSeeder;
use App\Catalog\Application\BuiltInProductRelationAttributesSeeder;
use App\Catalog\Domain\Entity\AttributeGroup;
use App\Catalog\Domain\Entity\ObjectTypeAttributeGroup;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\AttributeGroupRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * MODR-02 (#924) — `BuiltInProductMediaAttributesSeeder` creates an empty
 * "Multimedia" AttributeGroup, marks it system, and attaches it to Product
 * with `display_mode='tab'` (default from MODR-01 #923 column).
 */
final class BuiltInProductMediaAttributesSeederTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function seedCreatesMediaGroupAttachedToProductWithDefaultTabDisplayMode(): void
    {
        $tenant = $this->bootAndSeedTenant('modr02a');

        $created = self::getContainer()
            ->get(BuiltInProductMediaAttributesSeeder::class)
            ->seed($tenant);

        self::assertSame(1, $created);

        $group = self::getContainer()->get(AttributeGroupRepositoryInterface::class)
            ->findByCode('media', $tenant);
        self::assertInstanceOf(AttributeGroup::class, $group);
        self::assertTrue($group->isSystemGroup(), 'media group must be system');

        $junction = $this->findJunction($tenant, $group);
        self::assertInstanceOf(ObjectTypeAttributeGroup::class, $junction);
        self::assertSame('tab', $junction->getDisplayMode(), 'display_mode defaults to tab (MODR-01)');
    }

    #[Test]
    public function seedIsIdempotent(): void
    {
        $tenant = $this->bootAndSeedTenant('modr02b');
        $seeder = self::getContainer()->get(BuiltInProductMediaAttributesSeeder::class);

        $created = $seeder->seed($tenant);
        self::assertSame(1, $created, 'first run creates the group');

        $secondRun = $seeder->seed($tenant);
        self::assertSame(0, $secondRun, 'second run is a no-op');
    }

    #[Test]
    public function relationsGroupAlreadyHasDefaultTabDisplayMode(): void
    {
        $tenant = $this->bootAndSeedTenant('modr02c');

        self::getContainer()
            ->get(BuiltInProductRelationAttributesSeeder::class)
            ->seed($tenant);

        $group = self::getContainer()->get(AttributeGroupRepositoryInterface::class)
            ->findByCode('relations', $tenant);
        self::assertInstanceOf(AttributeGroup::class, $group);

        $junction = $this->findJunction($tenant, $group);
        self::assertInstanceOf(ObjectTypeAttributeGroup::class, $junction);
        self::assertSame('tab', $junction->getDisplayMode());
    }

    private function bootAndSeedTenant(string $code): Tenant
    {
        self::bootKernel();
        $em = self::getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);

        $tenant = new Tenant($code, 'MODR-02 tenant '.$code);
        $em->persist($tenant);
        $em->flush();

        $tenantContext = self::getContainer()->get(TenantContext::class);
        $tenantContext->set($tenant);

        self::getContainer()->get(BuiltInObjectTypeSeeder::class)->seed($tenant);

        return $tenant;
    }

    private function findJunction(Tenant $tenant, AttributeGroup $group): ?ObjectTypeAttributeGroup
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);

        $product = self::getContainer()->get(ObjectTypeRepositoryInterface::class)
            ->findBuiltInByKind(ObjectKind::Product, $tenant);
        \assert(null !== $product);

        $junction = $em->getRepository(ObjectTypeAttributeGroup::class)
            ->findOneBy(['objectType' => $product, 'attributeGroup' => $group]);

        return $junction instanceof ObjectTypeAttributeGroup ? $junction : null;
    }
}
