<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Doctrine\Repository;

use App\Identity\Domain\Entity\Permission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Permission>
 */
class PermissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Permission::class);
    }

    public function findByResourceAction(string $resource, string $action): ?Permission
    {
        return $this->findOneBy(['resource' => $resource, 'action' => $action]);
    }

    public function findByCode(string $code): ?Permission
    {
        return $this->findOneBy(['code' => $code]);
    }
}
