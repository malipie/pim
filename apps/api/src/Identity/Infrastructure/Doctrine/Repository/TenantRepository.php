<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Doctrine\Repository;

use App\Shared\Infrastructure\Doctrine\Repository\DoctrineTenantRepository;

/**
 * Backwards-compatibility shim for the Tenant repository, which moved out of
 * Identity into Shared (RF-02). Existing autowiring on type
 * `App\Identity\Infrastructure\Doctrine\Repository\TenantRepository` keeps
 * working because this subclass inherits everything from the new adapter.
 *
 * Removed in RF-04 once every consumer is updated to inject
 * App\Shared\Domain\Repository\TenantRepositoryInterface.
 *
 * @deprecated since RF-02 — use {@see DoctrineTenantRepository} or the
 *             {@see \App\Shared\Domain\Repository\TenantRepositoryInterface} port
 */
final class TenantRepository extends DoctrineTenantRepository
{
}
