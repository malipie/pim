<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog;

use App\Catalog\Application\BuiltInObjectTypeSeeder;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Infrastructure\Doctrine\Repository\ObjectTypeRepository;
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
        $tenant = $this->createTenant('demo');

        $created = $this->seeder()->seed($tenant);

        self::assertSame(3, $created);
        $repo = $this->repository();
        foreach ([ObjectKind::Product, ObjectKind::Category, ObjectKind::Asset] as $kind) {
            $type = $repo->findBuiltInByKind($kind, $tenant);
            self::assertNotNull($type, $kind->value);
            self::assertTrue($type->isBuiltIn());
            self::assertSame($kind, $type->getKind());
        }
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

    private function repository(): ObjectTypeRepository
    {
        return self::getContainer()->get(ObjectTypeRepository::class);
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
