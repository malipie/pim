<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\Repository;

use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectRelation;
use App\Catalog\Domain\Repository\ObjectRelationRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * Doctrine ORM implementation of {@see ObjectRelationRepositoryInterface}.
 *
 * The base `ServiceEntityRepository::findBy`-style methods carry the
 * TenantFilter automatically (the entity is `TenantScoped`); the two
 * domain-specific lookups below build their DQL on top of that, so
 * cross-tenant rows are never returned.
 *
 * @extends ServiceEntityRepository<ObjectRelation>
 */
final class DoctrineObjectRelationRepository extends ServiceEntityRepository implements ObjectRelationRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ObjectRelation::class);
    }

    public function add(ObjectRelation $relation): void
    {
        $this->getEntityManager()->persist($relation);
    }

    public function remove(ObjectRelation $relation): void
    {
        $this->getEntityManager()->remove($relation);
    }

    public function findById(Uuid $id): ?ObjectRelation
    {
        return parent::find($id->toRfc4122());
    }

    public function findBySourceAndAttribute(CatalogObject $source, Attribute $attribute): array
    {
        /** @var list<ObjectRelation> $rows */
        $rows = $this->createQueryBuilder('r')
            ->andWhere('r.source = :source')
            ->andWhere('r.attribute = :attribute')
            ->setParameter('source', $source)
            ->setParameter('attribute', $attribute)
            ->orderBy('r.position', 'ASC')
            ->addOrderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    public function findByTarget(CatalogObject $target): array
    {
        /** @var list<ObjectRelation> $rows */
        $rows = $this->createQueryBuilder('r')
            ->andWhere('r.target = :target')
            ->setParameter('target', $target)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    public function countByTarget(CatalogObject $target): int
    {
        $result = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.target = :target')
            ->setParameter('target', $target)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }
}
