<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Application;

use App\Identity\Application\CurrentTenantProvider;
use App\Shared\Application\Auth\ApiKeyPrincipal;
use App\Shared\Application\TenantAware;
use App\Shared\Domain\Tenant;
use App\Shared\Infrastructure\Doctrine\Repository\DoctrineTenantRepository;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

/**
 * AUD-064 (W3-5.2) — the unauthenticated `APP_DEFAULT_TENANT_CODE=demo`
 * fallback must never apply in a production posture.
 *
 * Resolution order (see {@see CurrentTenantProvider}):
 *   1. authenticated TenantAware user  → user's tenant (any env)
 *   2. API-key principal               → tenant by id (any env)
 *   3. env-default fallback            → ONLY outside prod (dev/test single
 *      tenant + fixtures rely on `demo`); in prod an unauthenticated request
 *      gets null (deny) — no silent tenant `demo` access.
 */
final class CurrentTenantProviderTest extends TestCase
{
    #[Test]
    public function prodPostureUnauthenticatedRequestGetsNoSilentDemoFallback(): void
    {
        $repository = $this->createMock(DoctrineTenantRepository::class);
        // The vulnerability: a default code resolving the `demo` tenant for an
        // anonymous caller. In prod the provider must never reach the lookup.
        $repository->expects(self::never())->method('findByCode');

        $provider = new CurrentTenantProvider(
            tokenStorage: new TokenStorage(),
            tenantRepository: $repository,
            defaultTenantCode: 'demo',
            environment: 'prod',
        );

        self::assertNull(
            $provider->getCurrent(),
            'An unauthenticated request in prod must not silently resolve the demo tenant.',
        );
    }

    #[Test]
    public function devPostureStillHonoursTheEnvDefaultFallback(): void
    {
        $tenant = new Tenant('demo', 'Demo');

        $repository = $this->createMock(DoctrineTenantRepository::class);
        $repository->method('findByCode')->with('demo')->willReturn($tenant);

        $provider = new CurrentTenantProvider(
            tokenStorage: new TokenStorage(),
            tenantRepository: $repository,
            defaultTenantCode: 'demo',
            environment: 'dev',
        );

        self::assertSame(
            $tenant,
            $provider->getCurrent(),
            'Dev/test keep the env-default fallback so single-tenant fixtures resolve.',
        );
    }

    #[Test]
    public function emptyDefaultCodeNeverFallsBackRegardlessOfEnvironment(): void
    {
        $repository = $this->createMock(DoctrineTenantRepository::class);
        $repository->expects(self::never())->method('findByCode');

        $provider = new CurrentTenantProvider(
            tokenStorage: new TokenStorage(),
            tenantRepository: $repository,
            defaultTenantCode: '',
            environment: 'dev',
        );

        self::assertNull($provider->getCurrent());
    }

    #[Test]
    public function authenticatedUserTenantWinsEvenInProd(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $user = new class($tenant) implements TenantAware, UserInterface {
            public function __construct(private readonly Tenant $tenant)
            {
            }

            public function getTenant(): Tenant
            {
                return $this->tenant;
            }

            public function getRoles(): array
            {
                return ['ROLE_USER'];
            }

            public function eraseCredentials(): void
            {
            }

            public function getUserIdentifier(): string
            {
                return 'kasia@alpha.test';
            }
        };

        $repository = $this->createMock(DoctrineTenantRepository::class);
        $repository->expects(self::never())->method('findByCode');

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main'));

        $provider = new CurrentTenantProvider(
            tokenStorage: $tokenStorage,
            tenantRepository: $repository,
            defaultTenantCode: 'demo',
            environment: 'prod',
        );

        self::assertSame(
            $tenant,
            $provider->getCurrent(),
            'An authenticated user always resolves to their own tenant — unchanged by AUD-064.',
        );
    }

    #[Test]
    public function apiKeyPrincipalTenantResolvesEvenInProd(): void
    {
        $tenantId = Uuid::v4();
        $tenant = new Tenant('beta', 'Beta');

        $principal = $this->createStub(ApiKeyPrincipal::class);
        $principal->method('tenantId')->willReturn($tenantId);

        $repository = $this->createMock(DoctrineTenantRepository::class);
        $repository->expects(self::once())->method('findById')->with($tenantId)->willReturn($tenant);
        $repository->expects(self::never())->method('findByCode');

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($principal, 'main'));

        $provider = new CurrentTenantProvider(
            tokenStorage: $tokenStorage,
            tenantRepository: $repository,
            defaultTenantCode: 'demo',
            environment: 'prod',
        );

        self::assertSame($tenant, $provider->getCurrent());
    }
}
