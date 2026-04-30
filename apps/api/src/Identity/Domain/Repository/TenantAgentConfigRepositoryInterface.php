<?php

declare(strict_types=1);

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Entity\TenantAgentConfig;
use App\Shared\Domain\Tenant;

interface TenantAgentConfigRepositoryInterface
{
    public function save(TenantAgentConfig $config): void;

    public function remove(TenantAgentConfig $config): void;

    /**
     * BYOK is 1:1 per tenant — at most one row per tenant id.
     */
    public function findForTenant(Tenant $tenant): ?TenantAgentConfig;
}
