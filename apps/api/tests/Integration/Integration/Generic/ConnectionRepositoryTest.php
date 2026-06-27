<?php

declare(strict_types=1);

namespace App\Tests\Integration\Integration\Generic;

use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Enum\AuthType;
use App\Integration\Generic\Domain\Repository\ConnectionRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use App\Shared\Infrastructure\Doctrine\Filter\TenantFilterConfigurator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * APIC-P1-01 — ConnectionRepository round-trip + 2-tenant cross-read = 0
 * (Doctrine TenantFilter on the TenantScoped Connection entity; Postgres RLS
 * is the defence-in-depth wall verified separately at the migration level).
 */
final class ConnectionRepositoryTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function savesAndFindsConnectionWithinTenant(): void
    {
        $alpha = $this->createTenant('alpha');
        $this->activateTenantFilter($alpha);

        $conn = new Connection('idosell', 'IdoSell PL', 'https://api.idosell.com', AuthType::ApiKey);
        $conn->assignTenant($alpha);
        $this->repo()->save($conn);

        $found = $this->repo()->findByCode($alpha, 'idosell');
        self::assertNotNull($found);
        self::assertSame('idosell', $found->getCode());
        self::assertSame(AuthType::ApiKey, $found->getAuthType());

        self::assertCount(1, $this->repo()->findByTenant($alpha));
    }

    #[Test]
    public function connectionsAreIsolatedAcrossTenants(): void
    {
        $alpha = $this->createTenant('alpha');
        $beta = $this->createTenant('beta');

        $this->activateTenantFilter($alpha);
        $alphaConn = new Connection('idosell', 'IdoSell', 'https://api.idosell.com');
        $alphaConn->assignTenant($alpha);
        $this->repo()->save($alphaConn);

        // Switch to beta: alpha's connection must be invisible.
        $this->activateTenantFilter($beta);
        self::assertCount(0, $this->repo()->findByTenant($alpha), 'beta context must not see alpha connections.');
        self::assertNull($this->repo()->findByCode($alpha, 'idosell'));
        self::assertCount(0, $this->repo()->findByTenant($beta));
    }

    private function repo(): ConnectionRepositoryInterface
    {
        return self::getContainer()->get(ConnectionRepositoryInterface::class);
    }

    private function tenantContext(): TenantContext
    {
        return self::getContainer()->get(TenantContext::class);
    }

    /**
     * KernelTestCase has no HTTP request lifecycle, so the active tenant must
     * be wired into the Doctrine filter manually after every context switch.
     */
    private function activateTenantFilter(Tenant $tenant): void
    {
        $this->tenantContext()->set($tenant);
        self::getContainer()->get(TenantFilterConfigurator::class)->apply();
    }

    private function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }

    private function createTenant(string $code): Tenant
    {
        $tenant = new Tenant($code, ucfirst($code).' Tenant');
        $em = $this->em();
        $em->persist($tenant);
        $em->flush();

        return $tenant;
    }
}
