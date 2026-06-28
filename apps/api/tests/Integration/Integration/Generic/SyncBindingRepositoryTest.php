<?php

declare(strict_types=1);

namespace App\Tests\Integration\Integration\Generic;

use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Entity\SyncBinding;
use App\Integration\Generic\Domain\Enum\AuthType;
use App\Integration\Generic\Domain\Enum\SyncDirection;
use App\Integration\Generic\Domain\Repository\ConnectionRepositoryInterface;
use App\Integration\Generic\Domain\Repository\SyncBindingRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use App\Shared\Infrastructure\Doctrine\Filter\TenantFilterConfigurator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * APIC-P3-01 — SyncBindingRepository round-trip + 2-tenant cross-read = 0
 * (Doctrine TenantFilter on the TenantScoped SyncBinding; Postgres RLS is the
 * defence-in-depth wall verified at the migration level).
 */
final class SyncBindingRepositoryTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function savesAndFindsBindingWithinTenant(): void
    {
        $alpha = $this->createTenant('alpha');
        $this->activateTenantFilter($alpha);

        $connection = $this->createConnection($alpha, 'idosell');
        $binding = new SyncBinding($connection, Uuid::v7(), SyncDirection::Bidirectional);
        $binding->assignTenant($alpha);
        $binding->setSchedule('0 3 * * *');
        $this->bindings()->save($binding);

        $found = $this->bindings()->findById($binding->getId());
        self::assertNotNull($found);
        self::assertSame(SyncDirection::Bidirectional, $found->getDirection());
        self::assertSame('0 3 * * *', $found->getSchedule());
        self::assertSame($connection->getId()->toRfc4122(), $found->getConnectionId()->toRfc4122());

        self::assertCount(1, $this->bindings()->findByConnection($connection));
        self::assertCount(1, $this->bindings()->findEnabled());
    }

    #[Test]
    public function bindingsAreIsolatedAcrossTenants(): void
    {
        $alpha = $this->createTenant('alpha');
        $beta = $this->createTenant('beta');

        $this->activateTenantFilter($alpha);
        $alphaConnection = $this->createConnection($alpha, 'idosell');
        $alphaBinding = new SyncBinding($alphaConnection, Uuid::v7());
        $alphaBinding->assignTenant($alpha);
        $this->bindings()->save($alphaBinding);

        // Switch to beta: alpha's binding is invisible through the TenantFilter (DQL).
        $this->activateTenantFilter($beta);
        self::assertCount(0, $this->bindings()->findByConnection($alphaConnection));
        self::assertCount(0, $this->bindings()->findEnabled());
    }

    private function bindings(): SyncBindingRepositoryInterface
    {
        return self::getContainer()->get(SyncBindingRepositoryInterface::class);
    }

    private function connections(): ConnectionRepositoryInterface
    {
        return self::getContainer()->get(ConnectionRepositoryInterface::class);
    }

    private function tenantContext(): TenantContext
    {
        return self::getContainer()->get(TenantContext::class);
    }

    private function createConnection(Tenant $tenant, string $code): Connection
    {
        $connection = new Connection($code, ucfirst($code), 'https://api.example.com', AuthType::ApiKey);
        $connection->assignTenant($tenant);
        $this->connections()->save($connection);

        return $connection;
    }

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
