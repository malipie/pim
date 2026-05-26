<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Catalog\Application\BuiltInObjectTypeSeeder;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class BuiltInObjectTypeSeederTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function seedCreatesThreeBuiltInObjectTypesForTenant(): void
    {
        // ADR-014 / MOD-10 (#902) — Brand was demoted from built-in to
        // tenant-territory. The seeder emits exactly Product / Category /
        // Asset; Brand is no longer in the DEFINITIONS map.
        $tenant = $this->createTenant('demo');

        $created = $this->seeder()->seed($tenant);

        self::assertSame(3, $created);
        $repo = $this->repository();
        foreach ([ObjectKind::Product, ObjectKind::Category, ObjectKind::Asset] as $kind) {
            $type = $repo->findBuiltInByKind($kind, $tenant);
            self::assertNotNull($type, $kind->value);
            self::assertTrue($type->isBuiltIn());
            self::assertTrue($type->isCodeImmutable(), $kind->value);
            self::assertFalse($type->isDeletable(), $kind->value);
            self::assertNotNull($type->getIcon(), $kind->value);
            self::assertNotNull($type->getColor(), $kind->value);
            self::assertSame($kind, $type->getKind());
        }
    }

    #[Test]
    public function isCategorizableFlagIsTrueOnlyForProduct(): void
    {
        $tenant = $this->createTenant('demo');

        $this->seeder()->seed($tenant);

        $repo = $this->repository();
        $product = $repo->findBuiltInByKind(ObjectKind::Product, $tenant);
        $category = $repo->findBuiltInByKind(ObjectKind::Category, $tenant);
        $asset = $repo->findBuiltInByKind(ObjectKind::Asset, $tenant);

        self::assertNotNull($product);
        self::assertNotNull($category);
        self::assertNotNull($asset);

        self::assertTrue($product->isCategorizable(), 'Product is the only built-in that opts into primary-category overlay');
        self::assertFalse($category->isCategorizable(), 'Category itself is not categorized — only base attributes apply');
        self::assertFalse($asset->isCategorizable(), 'Asset has its own DAM workflow, not category-driven');
    }

    #[Test]
    public function seedIsIdempotent(): void
    {
        $tenant = $this->createTenant('demo');
        $seeder = $this->seeder();

        self::assertSame(3, $seeder->seed($tenant));
        self::assertSame(0, $seeder->seed($tenant), 'second run should be a no-op');
    }

    #[Test]
    public function seedIsScopedPerTenant(): void
    {
        $alpha = $this->createTenant('alpha');
        $bravo = $this->createTenant('bravo');

        $seeder = $this->seeder();
        $seeder->seed($alpha);
        $seeder->seed($bravo);

        $repo = $this->repository();
        $alphaProduct = $repo->findBuiltInByKind(ObjectKind::Product, $alpha);
        $bravoProduct = $repo->findBuiltInByKind(ObjectKind::Product, $bravo);
        self::assertNotNull($alphaProduct);
        self::assertNotNull($bravoProduct);
        // Each tenant carries its own independent rows.
        self::assertNotSame(
            $alphaProduct->getId(),
            $bravoProduct->getId(),
        );
    }

    private function seeder(): BuiltInObjectTypeSeeder
    {
        return self::getContainer()->get(BuiltInObjectTypeSeeder::class);
    }

    private function repository(): ObjectTypeRepositoryInterface
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
