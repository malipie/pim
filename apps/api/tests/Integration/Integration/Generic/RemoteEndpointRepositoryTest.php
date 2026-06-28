<?php

declare(strict_types=1);

namespace App\Tests\Integration\Integration\Generic;

use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Entity\RemoteEndpoint;
use App\Integration\Generic\Domain\Enum\AuthType;
use App\Integration\Generic\Domain\Enum\RemoteEndpointRole;
use App\Integration\Generic\Domain\Repository\ConnectionRepositoryInterface;
use App\Integration\Generic\Domain\Repository\RemoteEndpointRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use App\Shared\Infrastructure\Doctrine\Filter\TenantFilterConfigurator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * APIC-P2-01 — RemoteEndpointRepository round-trip + 2-tenant cross-read = 0
 * (Doctrine TenantFilter on the TenantScoped RemoteEndpoint; Postgres RLS is
 * the defence-in-depth wall verified at the migration level).
 */
final class RemoteEndpointRepositoryTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function savesAndFindsEndpointWithinTenant(): void
    {
        $alpha = $this->createTenant('alpha');
        $this->activateTenantFilter($alpha);

        $connection = $this->createConnection($alpha, 'idosell');
        $endpoint = new RemoteEndpoint($connection, RemoteEndpointRole::ReadList, 'GET', '/products');
        $endpoint->assignTenant($alpha);
        $endpoint->setRecordSelector('$.results');
        $endpoint->setQueryParams(['limit' => '100']);
        $this->endpoints()->save($endpoint);

        $found = $this->endpoints()->findById($endpoint->getId());
        self::assertNotNull($found);
        self::assertSame(RemoteEndpointRole::ReadList, $found->getRole());
        self::assertSame('GET', $found->getHttpMethod());
        self::assertSame('/products', $found->getPathTemplate());
        self::assertSame('$.results', $found->getRecordSelector());
        self::assertSame(['strategy' => 'none'], $found->getPagination());
        self::assertSame($connection->getId()->toRfc4122(), $found->getConnectionId()->toRfc4122());

        self::assertCount(1, $this->endpoints()->findByConnection($connection));
        self::assertNotNull(
            $this->endpoints()->findByConnectionAndRole($connection, RemoteEndpointRole::ReadList),
        );
    }

    #[Test]
    public function endpointsAreIsolatedAcrossTenants(): void
    {
        $alpha = $this->createTenant('alpha');
        $beta = $this->createTenant('beta');

        $this->activateTenantFilter($alpha);
        $alphaConnection = $this->createConnection($alpha, 'idosell');
        $alphaEndpoint = new RemoteEndpoint($alphaConnection, RemoteEndpointRole::ReadList, 'GET', '/products');
        $alphaEndpoint->assignTenant($alpha);
        $this->endpoints()->save($alphaEndpoint);

        // Switch to beta: alpha's endpoint must be invisible through the
        // Doctrine TenantFilter (DQL). `findById` is intentionally not asserted
        // here — EntityManager::find() by PK serves from the identity map and
        // bypasses SQL filters; cross-tenant PK reads are walled by Postgres RLS
        // (verified at the migration level), not by the ORM filter.
        $this->activateTenantFilter($beta);
        self::assertCount(0, $this->endpoints()->findByConnection($alphaConnection));
        self::assertNull(
            $this->endpoints()->findByConnectionAndRole($alphaConnection, RemoteEndpointRole::ReadList),
        );
    }

    private function endpoints(): RemoteEndpointRepositoryInterface
    {
        return self::getContainer()->get(RemoteEndpointRepositoryInterface::class);
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
