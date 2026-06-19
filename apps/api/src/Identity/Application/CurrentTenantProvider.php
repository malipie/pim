<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Shared\Application\Auth\ApiKeyPrincipal;
use App\Shared\Application\TenantAware;
use App\Shared\Domain\Tenant;
use App\Shared\Infrastructure\Doctrine\Repository\DoctrineTenantRepository;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Resolves the current tenant for the active request / CLI invocation.
 *
 * Resolution order:
 *  1. Authenticated user implementing TenantAware  → user's tenant
 *  2. API-key principal                            → tenant looked up by id
 *  3. Environment override APP_DEFAULT_TENANT_CODE → tenant looked up by code
 *     (NON-PROD ONLY — see AUD-064 below)
 *  4. null → caller decides (usually means: throw before persisting)
 *
 * Auth wiring lands in ticket #4 (0.0.4) and the User↔Tenant relation in #24
 * (0.2.1); for Sprint 0 the env-override path is the operative one and the
 * smoke test (#12) verifies isolation by switching APP_DEFAULT_TENANT_CODE
 * between two seeded tenants.
 *
 * AUD-064 (W3-5.2): the unauthenticated env-default fallback is a dev/test
 * convenience (a single seeded `demo` tenant + fixtures rely on it). In a
 * production posture it is an anti-pattern — an anonymous caller would
 * silently inherit the `demo` tenant context. The fallback is therefore
 * gated to non-prod environments. In prod an unauthenticated request gets
 * null (deny); tenant scope comes exclusively from the authenticated
 * principal. A prod overlay additionally clears APP_DEFAULT_TENANT_CODE as
 * defence in depth, but the env gate holds even if that value leaks.
 */
final class CurrentTenantProvider
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly DoctrineTenantRepository $tenantRepository,
        private readonly ?string $defaultTenantCode = null,
        private readonly string $environment = 'prod',
    ) {
    }

    public function getCurrent(): ?Tenant
    {
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        if ($user instanceof TenantAware) {
            return $user->getTenant();
        }

        // API-key principals carry the tenant id only — fetch the
        // managed Tenant entity so the Doctrine TenantFilter sees a
        // hydrated row rather than a UUID string.
        if ($user instanceof ApiKeyPrincipal) {
            return $this->tenantRepository->findById($user->tenantId());
        }

        // AUD-064: the env-default fallback is a dev/test convenience only.
        // In prod an unauthenticated caller must NOT silently resolve a
        // tenant — deny by returning null so tenant scope can only come from
        // an authenticated principal.
        if ('prod' !== $this->environment && null !== $this->defaultTenantCode && '' !== $this->defaultTenantCode) {
            return $this->tenantRepository->findByCode($this->defaultTenantCode);
        }

        return null;
    }
}
