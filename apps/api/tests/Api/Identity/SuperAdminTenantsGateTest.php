<?php

declare(strict_types=1);

namespace App\Tests\Api\Identity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Identity\Application\RbacSeeder;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Rbac\RbacMatrix;
use App\Identity\Domain\Repository\RoleRepositoryInterface;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * AUD-003 (#1575) — the `/api/admin/tenants/*` operator panel exposes
 * cross-tenant platform metadata + tenant lifecycle controls. It must be
 * gated on the platform-level `platform.tenants.manage` permission, NOT
 * on the per-tenant `super_admin` role a regular Tenant Owner holds.
 *
 * Threat model: before the fix, fixtures granted the GLOBAL `super_admin`
 * role to every tenant Owner. The controller gated on that role code, so
 * Owner A could list every tenant (recon competitor metadata) and could
 * suspend/delete a competitor tenant.
 *
 * Invariants asserted here:
 *  - a Tenant Owner (global `super_admin` + per-tenant `tenant_owner`,
 *    exactly the fixtures shape) is FORBIDDEN from list / detail / write
 *    on the operator panel,
 *  - a dedicated platform operator (`platform_operator` global role with
 *    `platform.tenants.manage`) is ALLOWED.
 */
final class SuperAdminTenantsGateTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    protected static ?bool $alwaysBootKernel = true;

    private const string TENANT_A_CODE = 'acme';
    private const string TENANT_B_CODE = 'demo';

    private const string OWNER_A_EMAIL = 'owner@acme.localhost';
    private const string PLATFORM_OPERATOR_EMAIL = 'ops@platform.localhost';

    private string $tenantBId = '';

    protected function setUp(): void
    {
        parent::setUp();

        self::getContainer()->get('limiter.auth_login')->create('127.0.0.1')->reset();

        $em = $this->em();
        self::getContainer()->get(RbacSeeder::class)->seed();

        $roles = self::getContainer()->get(RoleRepositoryInterface::class);
        // The per-tenant Owner holds the GLOBAL super_admin role exactly as
        // AppFixtures + RbacSeeder grant it today (the vulnerable shape).
        $superAdmin = $roles->findGlobalByCode(RbacMatrix::ROLE_SUPER_ADMIN);
        $platformOperator = $roles->findGlobalByCode(RbacMatrix::ROLE_PLATFORM_OPERATOR);
        \assert(null !== $superAdmin, 'super_admin must exist after seeding.');
        \assert(null !== $platformOperator, 'platform_operator must exist after seeding.');

        $tenantA = new Tenant(self::TENANT_A_CODE, 'Acme Industries');
        $tenantB = new Tenant(self::TENANT_B_CODE, 'Demo Tenant');
        $em->persist($tenantA);
        $em->persist($tenantB);
        $em->flush();
        $this->tenantBId = $tenantB->getId()->toRfc4122();

        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        // Tenant Owner: global super_admin exactly as AppFixtures grants it
        // to every tenant's admin. That role code is what the panel gated on
        // before the fix — it must no longer grant platform access.
        $ownerA = $this->makeUser($tenantA, self::OWNER_A_EMAIL, $hasher);
        $ownerA->addRole($superAdmin);
        $em->persist($ownerA);

        // Platform operator lives in a tenant row too (every User needs a
        // tenant_id NOT NULL) but is the only principal holding the global
        // platform_operator role with platform.tenants.manage.
        $operator = $this->makeUser($tenantA, self::PLATFORM_OPERATOR_EMAIL, $hasher);
        $operator->addRole($platformOperator);
        $em->persist($operator);

        $em->flush();
    }

    #[Test]
    public function tenantOwnerIsForbiddenFromCrossTenantList(): void
    {
        $client = $this->clientFor(self::OWNER_A_EMAIL);
        $client->request('GET', '/api/admin/tenants');

        self::assertResponseStatusCodeSame(403);
        self::assertResponseHeaderSame('content-type', 'application/problem+json');
    }

    #[Test]
    public function tenantOwnerIsForbiddenFromCompetitorTenantDetail(): void
    {
        $client = $this->clientFor(self::OWNER_A_EMAIL);
        $client->request('GET', '/api/admin/tenants/'.$this->tenantBId);

        self::assertResponseStatusCodeSame(403);
        self::assertResponseHeaderSame('content-type', 'application/problem+json');
    }

    #[Test]
    public function tenantOwnerIsForbiddenFromSuspendingCompetitorTenant(): void
    {
        $client = $this->clientFor(self::OWNER_A_EMAIL);
        $client->request('POST', '/api/admin/tenants/'.$this->tenantBId.'/suspend');

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function tenantOwnerIsForbiddenFromDeletingCompetitorTenant(): void
    {
        $client = $this->clientFor(self::OWNER_A_EMAIL);
        $client->request('DELETE', '/api/admin/tenants/'.$this->tenantBId);

        self::assertResponseStatusCodeSame(403);
    }

    #[Test]
    public function platformOperatorCanListEveryTenant(): void
    {
        $client = $this->clientFor(self::PLATFORM_OPERATOR_EMAIL);
        $client->request('GET', '/api/admin/tenants');

        self::assertResponseStatusCodeSame(200);
        $response = $client->getResponse();
        \assert(null !== $response);
        /** @var array<string, mixed> $body */
        $body = $response->toArray();
        self::assertArrayHasKey('member', $body);
        self::assertSame(2, $body['totalItems'] ?? null);
    }

    #[Test]
    public function platformOperatorCanReadTenantDetail(): void
    {
        $client = $this->clientFor(self::PLATFORM_OPERATOR_EMAIL);
        $client->request('GET', '/api/admin/tenants/'.$this->tenantBId);

        self::assertResponseStatusCodeSame(200);
    }

    private function makeUser(Tenant $tenant, string $email, UserPasswordHasherInterface $hasher): User
    {
        $stub = new User($tenant, $email, '');

        return new User($tenant, $email, $hasher->hashPassword($stub, 'changeme'));
    }

    private function clientFor(string $email): Client
    {
        $user = self::getContainer()->get(UserRepositoryInterface::class)->findByEmail($email);
        \assert(null !== $user);
        $jwt = self::getContainer()->get(JWTTokenManagerInterface::class)->create($user);
        $client = static::createClient();
        $client->setDefaultOptions(['headers' => ['authorization' => 'Bearer '.$jwt]]);

        return $client;
    }

    private function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }
}
