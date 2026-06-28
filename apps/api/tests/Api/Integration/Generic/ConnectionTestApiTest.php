<?php

declare(strict_types=1);

namespace App\Tests\Api\Integration\Generic;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Identity\Domain\Entity\User;
use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Enum\AuthType;
use App\Integration\Generic\Domain\Enum\ConnectionStatus;
use App\Integration\Generic\Domain\Repository\ConnectionRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use App\Tests\Api\ApiConfigurator\ApiConfiguratorApiTestCase;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * APIC-P1-05 — POST /api/connections/{id}/test. Reuses the ApiConfigurator RBAC
 * scaffold (super_admin + tenant_owner admin, PRD + legacy permissions). The
 * probe target is a loopback host so the SSRF pre-filter rejects it before any
 * network I/O — the test is deterministic and offline; the happy probe path is
 * unit-covered in GenericRestClientTest.
 */
final class ConnectionTestApiTest extends ApiConfiguratorApiTestCase
{
    #[Test]
    public function unauthenticatedRequestIs401(): void
    {
        $id = $this->seedConnection('idosell');

        static::createClient()->request('POST', '/api/connections/'.$id.'/test');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    #[Test]
    public function unknownConnectionIs404(): void
    {
        $this->authenticatedClient()->request('POST', '/api/connections/01234567-1234-7000-8000-000000000000/test');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    #[Test]
    public function userWithoutIntegrationsPermissionIs403(): void
    {
        $id = $this->seedConnection('idosell');

        $this->limitedClient()->request('POST', '/api/connections/'.$id.'/test');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    #[Test]
    public function probeOfUnreachablePrivateHostReportsErrorAndRecordsHealth(): void
    {
        $id = $this->seedConnection('idosell');

        $body = $this->authenticatedClient()
            ->request('POST', '/api/connections/'.$id.'/test')
            ->toArray();

        self::assertFalse($body['ok'] ?? null);
        self::assertSame(ConnectionStatus::Error->value, $body['status'] ?? null);
        self::assertArrayHasKey('checked_at', $body);
        self::assertArrayHasKey('error', $body);

        $connection = $this->connectionRepo()->findByCode($this->demoTenant(), 'idosell');
        self::assertNotNull($connection);
        self::assertSame(ConnectionStatus::Error, $connection->getStatus());
        self::assertNotNull($connection->getLastHealthCheckAt());
    }

    private function seedConnection(string $code): string
    {
        self::getContainer()->get(TenantContext::class)->set($this->demoTenant());

        // Loopback target: rejected by the SSRF pre-filter before any I/O.
        $connection = new Connection($code, ucfirst($code), 'https://127.0.0.1:9', AuthType::None);
        $this->connectionRepo()->save($connection);

        return $connection->getId()->toRfc4122();
    }

    private function connectionRepo(): ConnectionRepositoryInterface
    {
        return self::getContainer()->get(ConnectionRepositoryInterface::class);
    }

    private function demoTenant(): Tenant
    {
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);

        return $tenant;
    }

    /**
     * A real authenticated user that lacks `settings.integrations.manage`
     * (ROLE_USER only, no RBAC roles) — exercises the 403 path.
     */
    private function limitedClient(): Client
    {
        $tenant = $this->demoTenant();
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $email = 'limited@demo.localhost';
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
