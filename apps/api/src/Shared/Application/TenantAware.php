<?php

declare(strict_types=1);

namespace App\Shared\Application;

use App\Shared\Domain\Tenant;

/**
 * Marker interface for users / services that carry tenant scope. Implemented
 * by the User entity (ticket #24, 0.2.1). CurrentTenantProvider checks for
 * this interface before falling back to the env-default tenant.
 */
interface TenantAware
{
    public function getTenant(): Tenant;
}
