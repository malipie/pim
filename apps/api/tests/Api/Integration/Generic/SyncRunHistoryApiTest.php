<?php

declare(strict_types=1);

namespace App\Tests\Api\Integration\Generic;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Identity\Domain\Entity\User;
use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Entity\SyncBinding;
use App\Integration\Generic\Domain\Entity\SyncRun;
use App\Integration\Generic\Domain\Entity\SyncRunLog;
use App\Integration\Generic\Domain\Enum\AuthType;
use App\Integration\Generic\Domain\Enum\SyncDirection;
use App\Integration\Generic\Domain\Enum\SyncRecordAction;
use App\Integration\Generic\Domain\Enum\SyncRunStatus;
use App\Integration\Generic\Domain\Repository\ConnectionRepositoryInterface;
use App\Integration\Generic\Domain\Repository\SyncBindingRepositoryInterface;
use App\Integration\Generic\Domain\Repository\SyncRunLogRepositoryInterface;
use App\Integration\Generic\Domain\Repository\SyncRunRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use App\Shared\Infrastructure\Doctrine\Filter\TenantFilterConfigurator;
use App\Tests\Api\ApiConfigurator\ApiConfiguratorApiTestCase;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * APIC-P4-01 — read coverage for the SyncRun history (`/api/sync_runs`) and the
 * per-record drill-down (`/api/sync_run_logs`). Runs/logs are seeded directly
 * (the sync engine owns their creation; there is no write API) under the demo
 * tenant, then queried through the public API.
 *
 * @phpstan-type SeedIds array{connectionId: string, bindingId: string, runId: string}
 */
final class SyncRunHistoryApiTest extends ApiConfiguratorApiTestCase
{
    #[Test]
    public function historyListsRunsForConnection(): void
    {
        $ids = $this->seedRun();

        $body = $this->authenticatedClient()
            ->request('GET', '/api/sync_runs?connection='.$ids['connectionId'])
            ->toArray();

        self::assertResponseStatusCodeSame(200);
        self::assertSame(1, $body['totalItems'] ?? null);
        $run = $this->firstMember($body);
        self::assertSame($ids['bindingId'], $run['bindingId'] ?? null);
        self::assertSame('success', $run['status'] ?? null);
        self::assertSame(2, $run['createdCount'] ?? null);
    }

    #[Test]
    public function historyListsRunsForBinding(): void
    {
        $ids = $this->seedRun();

        $body = $this->authenticatedClient()
            ->request('GET', '/api/sync_runs?binding='.$ids['bindingId'])
            ->toArray();

        self::assertResponseStatusCodeSame(200);
        self::assertSame(1, $body['totalItems'] ?? null);
    }

    #[Test]
    public function getSingleRunReturnsCounters(): void
    {
        $ids = $this->seedRun();

        $body = $this->authenticatedClient()
            ->request('GET', '/api/sync_runs/'.$ids['runId'])
            ->toArray();

        self::assertResponseStatusCodeSame(200);
        self::assertSame('inbound', $body['direction'] ?? null);
        self::assertSame(2, $body['createdCount'] ?? null);
        self::assertSame(1, $body['failedCount'] ?? null);
    }

    #[Test]
    public function drilldownListsLogsForRun(): void
    {
        $ids = $this->seedRun();

        $body = $this->authenticatedClient()
            ->request('GET', '/api/sync_run_logs?run='.$ids['runId'])
            ->toArray();

        self::assertResponseStatusCodeSame(200);
        self::assertSame(1, $body['totalItems'] ?? null);
        $log = $this->firstMember($body);
        self::assertSame('created', $log['action'] ?? null);
        self::assertSame('SKU-1', $log['matchKey'] ?? null);
    }

    #[Test]
    public function unauthenticatedListIs401(): void
    {
        static::createClient()->request('GET', '/api/sync_runs');
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    #[Test]
    public function limitedUserListIs403(): void
    {
        $this->limitedClient()->request('GET', '/api/sync_runs');
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * Narrow the first `member` row of a Hydra collection for offset access.
     *
     * @param array<array-key, mixed> $body
     *
     * @return array<array-key, mixed>
     */
    private function firstMember(array $body): array
    {
        $members = $body['member'] ?? [];
        self::assertIsArray($members);
        self::assertArrayHasKey(0, $members);
        $first = $members[0];
        self::assertIsArray($first);

        return $first;
    }

    /**
     * @return SeedIds
     */
    private function seedRun(): array
    {
        $tenant = $this->demoTenant();
        $this->activateTenantFilter($tenant);

        $connection = new Connection('idosell', 'IdoSell', 'https://api.example.test', AuthType::ApiKey);
        $connection->assignTenant($tenant);
        self::getContainer()->get(ConnectionRepositoryInterface::class)->save($connection);

        $binding = new SyncBinding($connection, Uuid::v7(), SyncDirection::Inbound);
        $binding->assignTenant($tenant);
        self::getContainer()->get(SyncBindingRepositoryInterface::class)->save($binding);

        $run = new SyncRun($binding, SyncDirection::Inbound);
        $run->assignTenant($tenant);
        $run->recordCreated();
        $run->recordCreated();
        $run->recordFailed();
        $run->markFinished(SyncRunStatus::Success, ['state' => '2026-06-02']);
        self::getContainer()->get(SyncRunRepositoryInterface::class)->save($run);

        $log = new SyncRunLog($run, SyncRecordAction::Created);
        $log->assignTenant($tenant);
        $log->setMatchKey('SKU-1');
        $log->setFields(['sku' => 'SKU-1']);
        self::getContainer()->get(SyncRunLogRepositoryInterface::class)->save($log);

        return [
            'connectionId' => $connection->getId()->toRfc4122(),
            'bindingId' => $binding->getId()->toRfc4122(),
            'runId' => $run->getId()->toRfc4122(),
        ];
    }

    private function demoTenant(): Tenant
    {
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);

        return $tenant;
    }

    private function activateTenantFilter(Tenant $tenant): void
    {
        self::getContainer()->get(TenantContext::class)->set($tenant);
        self::getContainer()->get(TenantFilterConfigurator::class)->apply();
    }

    private function limitedClient(): Client
    {
        $tenant = $this->demoTenant();

        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $email = 'limited-run@demo.localhost';
        $stub = new User($tenant, $email, '', ['ROLE_USER']);
        $user = new User($tenant, $email, $hasher->hashPassword($stub, 'changeme'), ['ROLE_USER']);
        $em = $this->em();
        $em->persist($user);
        $em->flush();

        $jwt = self::getContainer()->get(JWTTokenManagerInterface::class)->create($user);

        $client = static::createClient();
        $client->setDefaultOptions(['headers' => ['authorization' => 'Bearer '.$jwt]]);

        return $client;
    }
}
