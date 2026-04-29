<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Shared\Domain\Tenant;

/**
 * Marker interface for domain entities that carry a `tenant_id` column and
 * must be auto-stamped + filtered against the current tenant.
 *
 * Implementing this interface opts an entity into:
 *   - {@see \App\Identity\Infrastructure\Doctrine\EventListener\TenantAssignmentListener}:
 *     stamps the active tenant on `prePersist` when the entity has none yet,
 *     by calling {@see assignTenant()}.
 *   - {@see \App\Identity\Infrastructure\Doctrine\Filter\TenantFilter}:
 *     appends `WHERE tenant_id = :current_tenant` to every query.
 *
 * Distinct from {@see TenantAware}, which says "this object can resolve the
 * current Tenant" (used by CurrentTenantProvider on User-like principals).
 * A class can implement both; most domain entities (Product, future
 * Object/Channel/Asset) only need TenantScoped.
 *
 * Why two methods instead of just `getTenant`? The setter is part of the
 * contract because the listener must be able to assign the tenant in a
 * type-safe way without reflection or duck-typing. Concrete entities can
 * still wrap the assignment in domain rules (e.g. throw on re-assignment)
 * — the listener calls the public method and they decide.
 */
interface TenantScoped
{
    public function getTenant(): ?Tenant;

    public function assignTenant(Tenant $tenant): void;
}
