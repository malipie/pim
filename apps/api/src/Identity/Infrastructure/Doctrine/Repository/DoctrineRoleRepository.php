<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Doctrine\Repository;

use App\Identity\Domain\Entity\Role;
use App\Identity\Domain\Repository\RoleRepositoryInterface;
use App\Shared\Domain\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Role>
 */
class DoctrineRoleRepository extends ServiceEntityRepository implements RoleRepositoryInterface
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

    public function findById(\Symfony\Component\Uid\Uuid $id): ?Role
    {
        return parent::find($id->toRfc4122());
    }

    public function save(Role $entity): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
    }

    public function remove(Role $entity): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        $em->flush();
    }
}
