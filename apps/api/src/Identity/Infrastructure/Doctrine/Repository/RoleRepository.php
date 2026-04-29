<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Doctrine\Repository;

use App\Identity\Domain\Entity\Role;
use App\Shared\Domain\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Role>
 */
class RoleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Role::class);
    }

    public function findGlobalByCode(string $code): ?Role
    {
        return $this->findOneBy(['code' => $code, 'tenant' => null]);
    }

    public function findByCode(string $code, ?Tenant $tenant = null): ?Role
    {
        return $this->findOneBy(['code' => $code, 'tenant' => $tenant]);
    }
}
