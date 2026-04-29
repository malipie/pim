<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Shared\Application\TenantAware;
use App\Shared\Domain\Tenant;
use App\Shared\Infrastructure\Doctrine\Repository\DoctrineTenantRepository;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Resolves the current tenant for the active request / CLI invocation.
 *
 * Resolution order:
 *  1. Authenticated user implementing TenantAware  → user's tenant
 *  2. Environment override APP_DEFAULT_TENANT_CODE → tenant looked up by code
 *  3. null → caller decides (usually means: throw before persisting)
 *
 * Auth wiring lands in ticket #4 (0.0.4) and the User↔Tenant relation in #24
 * (0.2.1); for Sprint 0 the env-override path is the operative one and the
 * smoke test (#12) verifies isolation by switching APP_DEFAULT_TENANT_CODE
 * between two seeded tenants.
 */
final class CurrentTenantProvider
{
    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly DoctrineTenantRepository $tenantRepository,
        private readonly ?string $defaultTenantCode = null,
    ) {
    }

    public function getCurrent(): ?Tenant
    {
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        if ($user instanceof TenantAware) {
            return $user->getTenant();
        }

        if (null !== $this->defaultTenantCode && '' !== $this->defaultTenantCode) {
            return $this->tenantRepository->findByCode($this->defaultTenantCode);
        }

        return null;
    }
}
