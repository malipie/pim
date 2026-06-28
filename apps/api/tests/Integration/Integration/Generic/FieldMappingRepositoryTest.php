<?php

declare(strict_types=1);

namespace App\Tests\Integration\Integration\Generic;

use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Entity\FieldMapping;
use App\Integration\Generic\Domain\Enum\AuthType;
use App\Integration\Generic\Domain\Enum\MappingDirection;
use App\Integration\Generic\Domain\Repository\ConnectionRepositoryInterface;
use App\Integration\Generic\Domain\Repository\FieldMappingRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use App\Shared\Infrastructure\Doctrine\Filter\TenantFilterConfigurator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * APIC-P2-07 — FieldMappingRepository round-trip + 2-tenant cross-read = 0
 * (Doctrine TenantFilter on the TenantScoped FieldMapping; Postgres RLS is the
 * defence-in-depth wall verified at the migration level).
 */
final class FieldMappingRepositoryTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function savesAndFindsMappingWithinTenant(): void
    {
        $alpha = $this->createTenant('alpha');
        $this->activateTenantFilter($alpha);

        $connection = $this->createConnection($alpha, 'idosell');
        $mapping = new FieldMapping($connection, 'sku', '$.sku', MappingDirection::Both);
        $mapping->assignTenant($alpha);
        $mapping->setMatchKey(true);
        $this->mappings()->save($mapping);

        $found = $this->mappings()->findById($mapping->getId());
        self::assertNotNull($found);
        self::assertSame('sku', $found->getPimTarget());
        self::assertSame('$.sku', $found->getRemoteFieldPath());
        self::assertSame(MappingDirection::Both, $found->getDirection());
        self::assertTrue($found->isMatchKey());
        self::assertSame(1, $found->getVersion());

        self::assertCount(1, $this->mappings()->findByConnection($connection));
        self::assertNotNull($this->mappings()->findByConnectionAndTarget($connection, 'sku'));
    }

    #[Test]
    public function mappingsAreIsolatedAcrossTenants(): void
    {
        $alpha = $this->createTenant('alpha');
        $beta = $this->createTenant('beta');

        $this->activateTenantFilter($alpha);
        $alphaConnection = $this->createConnection($alpha, 'idosell');
        $alphaMapping = new FieldMapping($alphaConnection, 'sku', '$.sku');
        $alphaMapping->assignTenant($alpha);
        $this->mappings()->save($alphaMapping);

        // Switch to beta: alpha's mapping is invisible through the TenantFilter (DQL).
        $this->activateTenantFilter($beta);
        self::assertCount(0, $this->mappings()->findByConnection($alphaConnection));
        self::assertNull($this->mappings()->findByConnectionAndTarget($alphaConnection, 'sku'));
    }

    private function mappings(): FieldMappingRepositoryInterface
    {
        return self::getContainer()->get(FieldMappingRepositoryInterface::class);
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
