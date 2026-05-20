<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Doctrine\Repository;

use App\Identity\Domain\Entity\Permission;
use App\Identity\Domain\Repository\PermissionRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Permission>
 */
class DoctrinePermissionRepository extends ServiceEntityRepository implements PermissionRepositoryInterface
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

    public function findById(\Symfony\Component\Uid\Uuid $id): ?Permission
    {
        return parent::find($id->toRfc4122());
    }

    public function save(Permission $entity): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
    }

    public function remove(Permission $entity): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        $em->flush();
    }

    public function findAllOrdered(): array
    {
        /** @var list<Permission> $result */
        $result = $this->createQueryBuilder('p')
            ->orderBy('p.code', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
