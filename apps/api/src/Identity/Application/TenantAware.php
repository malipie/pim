<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Domain\Entity\Tenant;

/**
 * Marker interface for users / services that carry tenant scope. Implemented
 * by the User entity (ticket #24, 0.2.1). CurrentTenantProvider checks for
 * this interface before falling back to the env-default tenant.
 */
interface TenantAware
{
    public function getTenant(): Tenant;
}
