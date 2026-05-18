<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Doctrine\Repository;

use App\Identity\Domain\Entity\SuperAdmin;
use App\Identity\Domain\Repository\SuperAdminRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<SuperAdmin>
 */
class DoctrineSuperAdminRepository extends ServiceEntityRepository implements SuperAdminRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SuperAdmin::class);
    }

    public function findById(Uuid $id): ?SuperAdmin
    {
        return parent::find($id->toRfc4122());
    }

    public function findByEmail(string $email): ?SuperAdmin
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function save(SuperAdmin $entity): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
    }

    public function remove(SuperAdmin $entity): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        $em->flush();
    }
}
