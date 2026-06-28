<?php

declare(strict_types=1);

namespace App\Tests\Integration\Integration\Generic;

use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Entity\RemoteEndpoint;
use App\Integration\Generic\Domain\Entity\RemoteField;
use App\Integration\Generic\Domain\Enum\AuthType;
use App\Integration\Generic\Domain\Enum\RemoteEndpointRole;
use App\Integration\Generic\Domain\Enum\RemoteFieldDataType;
use App\Integration\Generic\Domain\Repository\ConnectionRepositoryInterface;
use App\Integration\Generic\Domain\Repository\RemoteEndpointRepositoryInterface;
use App\Integration\Generic\Domain\Repository\RemoteFieldRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use App\Shared\Infrastructure\Doctrine\Filter\TenantFilterConfigurator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * APIC-P2-02 — RemoteFieldRepository round-trip + 2-tenant cross-read = 0
 * (Doctrine TenantFilter on the TenantScoped RemoteField; Postgres RLS is the
 * defence-in-depth wall verified at the migration level).
 */
final class RemoteFieldRepositoryTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function savesAndFindsFieldWithinTenant(): void
    {
        $alpha = $this->createTenant('alpha');
        $this->activateTenantFilter($alpha);

        $endpoint = $this->createEndpoint($alpha);
        $field = new RemoteField($endpoint, '$.price', RemoteFieldDataType::Number);
        $field->assignTenant($alpha);
        $field->setLabel('Price');
        $field->setSampleValue('19.99');
        $this->fields()->save($field);

        $found = $this->fields()->findById($field->getId());
        self::assertNotNull($found);
        self::assertSame('$.price', $found->getPath());
        self::assertSame('Price', $found->getLabel());
        self::assertSame(RemoteFieldDataType::Number, $found->getDataType());
        self::assertSame('19.99', $found->getSampleValue());
        self::assertSame($endpoint->getId()->toRfc4122(), $found->getEndpointId()->toRfc4122());

        self::assertCount(1, $this->fields()->findByEndpoint($endpoint));
    }

    #[Test]
    public function fieldsAreIsolatedAcrossTenants(): void
    {
        $alpha = $this->createTenant('alpha');
        $beta = $this->createTenant('beta');

        $this->activateTenantFilter($alpha);
        $alphaEndpoint = $this->createEndpoint($alpha);
        $alphaField = new RemoteField($alphaEndpoint, '$.sku', RemoteFieldDataType::String);
        $alphaField->assignTenant($alpha);
        $this->fields()->save($alphaField);

        // Switch to beta: alpha's field is invisible through the Doctrine
        // TenantFilter (DQL). `findById` (PK → identity map) bypasses SQL
        // filters and is walled by Postgres RLS, not asserted here.
        $this->activateTenantFilter($beta);
        self::assertCount(0, $this->fields()->findByEndpoint($alphaEndpoint));
    }

    private function fields(): RemoteFieldRepositoryInterface
    {
        return self::getContainer()->get(RemoteFieldRepositoryInterface::class);
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

    private function createEndpoint(Tenant $tenant): RemoteEndpoint
    {
        $connection = new Connection('idosell', 'IdoSell', 'https://api.idosell.com', AuthType::ApiKey);
        $connection->assignTenant($tenant);
        $this->connections()->save($connection);

        $endpoint = new RemoteEndpoint($connection, RemoteEndpointRole::ReadList, 'GET', '/products');
        $endpoint->assignTenant($tenant);
        $this->endpoints()->save($endpoint);

        return $endpoint;
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
