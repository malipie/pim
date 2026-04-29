<?php

declare(strict_types=1);

namespace App\Shared\Application;

use App\Shared\Domain\Tenant;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Mutable tenant context shared by the assignment listener and the SQL filter.
 *
 * Why a context object rather than reading from CurrentTenantProvider directly
 * inside the SQL filter? Doctrine instantiates SQL filters lazily and may not
 * have the security token yet (e.g. during early boot, fixtures, the CLI).
 * The context is set explicitly at request entry by an event subscriber and
 * by fixtures / tests; the filter and listener read it back without reaching
 * into the security stack at SQL-build time.
 */
final class TenantContext implements ResetInterface
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function clear(): void
    {
        $this->tenant = null;
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    /**
     * Symfony's `kernel.reset` hook — fires at the end of every request in
     * worker mode. Without it the singleton TenantContext keeps the previous
     * request's tenant alive across the next one and the SQL filter would
     * read stale state until RequestTenantSubscriber overwrites it.
     */
    public function reset(): void
    {
        $this->tenant = null;
    }
}
