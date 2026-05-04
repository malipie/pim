<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Entity\MenuConfiguration;
use App\Shared\Domain\Tenant;

interface MenuConfigurationRepositoryInterface
{
    public function findByTenant(Tenant $tenant): ?MenuConfiguration;
}
