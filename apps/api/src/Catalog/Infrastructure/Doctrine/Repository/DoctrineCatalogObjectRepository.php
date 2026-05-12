<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\Repository;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Shared\Domain\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CatalogObject>
 */
class DoctrineCatalogObjectRepository extends ServiceEntityRepository implements CatalogObjectRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CatalogObject::class);
    }

    public function findByCode(string $code, ObjectKind $kind, Tenant $tenant): ?CatalogObject
    {
        return $this->findOneBy(['code' => $code, 'kind' => $kind, 'tenant' => $tenant]);
    }

    /**
     * @return list<CatalogObject>
     */
    public function findByKind(ObjectKind $kind, Tenant $tenant): array
    {
        /** @var list<CatalogObject> $rows */
        $rows = $this->findBy(['kind' => $kind, 'tenant' => $tenant]);

        return $rows;
    }

    public function findById(\Symfony\Component\Uid\Uuid $id): ?CatalogObject
    {
        return parent::find($id->toRfc4122());
    }

    /**
     * @param list<string> $idsRfc4122
     *
     * @return list<CatalogObject>
     */
    public function findByIds(array $idsRfc4122): array
    {
        if ([] === $idsRfc4122) {
            return [];
        }

        /** @var list<CatalogObject> $rows */
        $rows = $this->createQueryBuilder('o')
            ->where('o.id IN (:ids)')
            ->setParameter('ids', $idsRfc4122)
            ->getQuery()
            ->getResult();

        return $rows;
    }

    public function save(CatalogObject $entity): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
    }

    public function remove(CatalogObject $entity): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        $em->flush();
    }
}
