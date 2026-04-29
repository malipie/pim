<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog;

use App\Catalog\Application\BuiltInAssociationTypeSeeder;
use App\Catalog\Domain\Repository\AssociationTypeRepositoryInterface;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class AssociationTypeSeederTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function seedCreatesFourDefaultsForTenant(): void
    {
        $tenant = $this->createTenant('demo');

        $created = $this->seeder()->seed($tenant);

        // Migration already seeds for existing tenants; we created a fresh
        // tenant here, so the seeder picks up all four.
        self::assertSame(4, $created);
        $repo = $this->repository();
        foreach (['cross_sell', 'up_sell', 'related', 'accessories'] as $code) {
            self::assertNotNull($repo->findByCode($code, $tenant), $code);
        }
    }

    #[Test]
    public function seedIsIdempotent(): void
    {
        $tenant = $this->createTenant('demo');
        $seeder = $this->seeder();

        self::assertSame(4, $seeder->seed($tenant));
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
        $alphaCross = $repo->findByCode('cross_sell', $alpha);
        $bravoCross = $repo->findByCode('cross_sell', $bravo);
        self::assertNotNull($alphaCross);
        self::assertNotNull($bravoCross);
        self::assertNotSame($alphaCross->getId(), $bravoCross->getId());
    }

    private function seeder(): BuiltInAssociationTypeSeeder
    {
        return self::getContainer()->get(BuiltInAssociationTypeSeeder::class);
    }

    private function repository(): AssociationTypeRepositoryInterface
    {
        return self::getContainer()->get(AssociationTypeRepositoryInterface::class);
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
