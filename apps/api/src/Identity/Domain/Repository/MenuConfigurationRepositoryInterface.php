<?php

declare(strict_types=1);

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Entity\MenuConfiguration;
use App\Shared\Domain\Tenant;

interface MenuConfigurationRepositoryInterface
{
    public function findByTenant(Tenant $tenant): ?MenuConfiguration;
}
