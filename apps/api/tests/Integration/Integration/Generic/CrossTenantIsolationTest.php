<?php

declare(strict_types=1);

namespace App\Tests\Integration\Integration\Generic;

use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Entity\FieldMapping;
use App\Integration\Generic\Domain\Entity\RemoteEndpoint;
use App\Integration\Generic\Domain\Entity\RemoteField;
use App\Integration\Generic\Domain\Entity\SyncBinding;
use App\Integration\Generic\Domain\Entity\SyncRun;
use App\Integration\Generic\Domain\Entity\SyncRunLog;
use App\Integration\Generic\Domain\Enum\AuthType;
use App\Integration\Generic\Domain\Enum\MappingDirection;
use App\Integration\Generic\Domain\Enum\RemoteEndpointRole;
use App\Integration\Generic\Domain\Enum\RemoteFieldDataType;
use App\Integration\Generic\Domain\Enum\SyncDirection;
use App\Integration\Generic\Domain\Enum\SyncRecordAction;
use App\Integration\Generic\Domain\Repository\ConnectionRepositoryInterface;
use App\Integration\Generic\Domain\Repository\FieldMappingRepositoryInterface;
use App\Integration\Generic\Domain\Repository\RemoteEndpointRepositoryInterface;
use App\Integration\Generic\Domain\Repository\RemoteFieldRepositoryInterface;
use App\Integration\Generic\Domain\Repository\SyncBindingRepositoryInterface;
use App\Integration\Generic\Domain\Repository\SyncRunLogRepositoryInterface;
use App\Integration\Generic\Domain\Repository\SyncRunRepositoryInterface;
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
 * APIC-P5-01 — consolidated Layer-3 cross-tenant isolation for EVERY consumer
 * entity (Connection, RemoteEndpoint, RemoteField, FieldMapping, SyncBinding,
 * SyncRun, SyncRunLog). Seeds the full graph under tenant `alpha`, then rebinds
 * to `beta` and asserts every read returns nothing — the Doctrine TenantFilter
 * (and, in production, Postgres FORCE RLS) hides another tenant's rows.
 *
 * Worker-side RLS (the async GUC path) is proven by
 * {@see InboundSyncWorkerContextTest}; the SyncRun/SyncRunLog rows asserted here
 * are exactly what that worker writes, so their cross-tenant invisibility closes
 * AC-2 at the data layer.
 *
 * @phpstan-type SeedRefs array{connection: Connection, endpoint: RemoteEndpoint, field: RemoteField, mapping: FieldMapping, binding: SyncBinding, run: SyncRun, log: SyncRunLog}
 */
final class CrossTenantIsolationTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function everyConsumerEntityIsInvisibleAcrossTenants(): void
    {
        $alpha = $this->createTenant('alpha');
        $beta = $this->createTenant('beta');

        $this->activateTenantFilter($alpha);
        $seed = $this->seedGraph($alpha);

        // Rebind to a different tenant — alpha's whole graph must vanish.
        $this->activateTenantFilter($beta);

        self::assertNull($this->connections()->findById($seed['connection']->getId()), 'Connection leaked');
        self::assertNull($this->endpoints()->findById($seed['endpoint']->getId()), 'RemoteEndpoint leaked');
        self::assertNull($this->fields()->findById($seed['field']->getId()), 'RemoteField leaked');
        self::assertNull($this->mappings()->findById($seed['mapping']->getId()), 'FieldMapping leaked');
        self::assertNull($this->bindings()->findById($seed['binding']->getId()), 'SyncBinding leaked');
        self::assertNull($this->runs()->findById($seed['run']->getId()), 'SyncRun leaked');

        // Collection reads scoped to the other tenant's parents also see nothing.
        self::assertCount(0, $this->endpoints()->findByConnection($seed['connection']));
        self::assertCount(0, $this->fields()->findByEndpoint($seed['endpoint']));
        self::assertCount(0, $this->mappings()->findByConnection($seed['connection']));
        self::assertCount(0, $this->bindings()->findByConnection($seed['connection']));
        self::assertCount(0, $this->bindings()->findEnabled());
        self::assertCount(0, $this->runs()->findByBinding($seed['binding']));
        self::assertCount(0, $this->logs()->findByRun($seed['run']));
    }

    #[Test]
    public function ownTenantStillReadsItsOwnGraph(): void
    {
        // Control: the isolation must not be a false positive that hides own rows.
        $alpha = $this->createTenant('alpha');
        $this->activateTenantFilter($alpha);
        $seed = $this->seedGraph($alpha);

        self::assertNotNull($this->connections()->findById($seed['connection']->getId()));
        self::assertNotNull($this->bindings()->findById($seed['binding']->getId()));
        self::assertCount(1, $this->runs()->findByBinding($seed['binding']));
        self::assertCount(1, $this->logs()->findByRun($seed['run']));
    }

    /**
     * @return SeedRefs
     */
    private function seedGraph(Tenant $tenant): array
    {
        $connection = new Connection('idosell', 'IdoSell', 'https://api.example.test', AuthType::ApiKey);
        $connection->assignTenant($tenant);
        $this->connections()->save($connection);

        $endpoint = new RemoteEndpoint($connection, RemoteEndpointRole::ReadList, 'GET', '/products');
        $endpoint->assignTenant($tenant);
        $this->endpoints()->save($endpoint);

        $field = new RemoteField($endpoint, '$.sku', RemoteFieldDataType::String);
        $field->assignTenant($tenant);
        $this->fields()->save($field);

        $mapping = new FieldMapping($connection, 'sku', '$.sku', MappingDirection::Both);
        $mapping->assignTenant($tenant);
        $this->mappings()->save($mapping);

        $binding = new SyncBinding($connection, Uuid::v7(), SyncDirection::Inbound);
        $binding->assignTenant($tenant);
        $this->bindings()->save($binding);

        $run = new SyncRun($binding, SyncDirection::Inbound);
        $run->assignTenant($tenant);
        $this->runs()->save($run);

        $log = new SyncRunLog($run, SyncRecordAction::Created);
        $log->assignTenant($tenant);
        $this->logs()->save($log);

        return [
            'connection' => $connection,
            'endpoint' => $endpoint,
            'field' => $field,
            'mapping' => $mapping,
            'binding' => $binding,
            'run' => $run,
            'log' => $log,
        ];
    }

    private function createTenant(string $code): Tenant
    {
        $tenant = new Tenant($code, ucfirst($code).' Tenant');
        $em = $this->em();
        $em->persist($tenant);
        $em->flush();

        return $tenant;
    }

    private function activateTenantFilter(Tenant $tenant): void
    {
        self::getContainer()->get(TenantContext::class)->set($tenant);
        self::getContainer()->get(TenantFilterConfigurator::class)->apply();
    }

    private function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }

    private function connections(): ConnectionRepositoryInterface
    {
        return self::getContainer()->get(ConnectionRepositoryInterface::class);
    }

    private function endpoints(): RemoteEndpointRepositoryInterface
    {
        return self::getContainer()->get(RemoteEndpointRepositoryInterface::class);
    }

    private function fields(): RemoteFieldRepositoryInterface
    {
        return self::getContainer()->get(RemoteFieldRepositoryInterface::class);
    }

    private function mappings(): FieldMappingRepositoryInterface
    {
        return self::getContainer()->get(FieldMappingRepositoryInterface::class);
    }

    private function bindings(): SyncBindingRepositoryInterface
    {
        return self::getContainer()->get(SyncBindingRepositoryInterface::class);
    }

    private function runs(): SyncRunRepositoryInterface
    {
        return self::getContainer()->get(SyncRunRepositoryInterface::class);
    }

    private function logs(): SyncRunLogRepositoryInterface
    {
        return self::getContainer()->get(SyncRunLogRepositoryInterface::class);
    }
}
