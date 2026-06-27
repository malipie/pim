<?php

declare(strict_types=1);

namespace App\Integration\Generic\Domain\Repository;

use App\Integration\Generic\Domain\Entity\Connection;
use App\Shared\Domain\Tenant;
use Symfony\Component\Uid\Uuid;

interface ConnectionRepositoryInterface
{
    public function save(Connection $connection): void;

    public function remove(Connection $connection): void;

    public function findById(Uuid $id): ?Connection;

    public function findByCode(Tenant $tenant, string $code): ?Connection;

    /**
     * @return list<Connection>
     */
    public function findByTenant(Tenant $tenant): array;
}
