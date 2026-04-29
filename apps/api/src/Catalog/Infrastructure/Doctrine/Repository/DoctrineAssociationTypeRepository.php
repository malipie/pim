<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\Repository;

use App\Catalog\Domain\Entity\AssociationType;
use App\Catalog\Domain\Repository\AssociationTypeRepositoryInterface;
use App\Shared\Domain\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AssociationType>
 */
class DoctrineAssociationTypeRepository extends ServiceEntityRepository implements AssociationTypeRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AssociationType::class);
    }

    public function findByCode(string $code, Tenant $tenant): ?AssociationType
    {
        return $this->findOneBy(['code' => $code, 'tenant' => $tenant]);
    }

    public function findById(\Symfony\Component\Uid\Uuid $id): ?AssociationType
    {
        return parent::find($id->toRfc4122());
    }

    public function save(AssociationType $entity): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
    }

    public function remove(AssociationType $entity): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        $em->flush();
    }
}
