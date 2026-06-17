<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\Repository;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectType;
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

    public function findByCodeInObjectTypes(string $code, array $objectTypeIds, Tenant $tenant): ?CatalogObject
    {
        $qb = $this->createQueryBuilder('o')
            ->where('o.code = :code')
            ->andWhere('o.tenant = :tenant')
            ->setParameter('code', $code)
            ->setParameter('tenant', $tenant)
            ->setMaxResults(1);

        if ([] !== $objectTypeIds) {
            $qb->andWhere('o.objectType IN (:ots)')->setParameter('ots', $objectTypeIds);
        }

        /** @var ?CatalogObject $result */
        $result = $qb->getQuery()->getOneOrNullResult();

        return $result;
    }

    /**
     * @param list<string> $parentIdsRfc4122
     *
     * @return list<CatalogObject>
     */
    public function findChildrenByParentIds(array $parentIdsRfc4122, Tenant $tenant): array
    {
        if ([] === $parentIdsRfc4122) {
            return [];
        }

        /** @var list<CatalogObject> $rows */
        $rows = $this->createQueryBuilder('o')
            ->where('o.parent IN (:parents)')
            ->andWhere('o.tenant = :tenant')
            ->setParameter('parents', $parentIdsRfc4122)
            ->setParameter('tenant', $tenant)
            ->orderBy('o.code', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
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

    /**
     * @return list<CatalogObject>
     */
    public function findByObjectType(ObjectType $objectType, Tenant $tenant): array
    {
        /** @var list<CatalogObject> $rows */
        $rows = $this->findBy(['objectType' => $objectType, 'tenant' => $tenant]);

        return $rows;
    }

    /**
     * IMP2-2.6 — one keyset page of ROOT objects (no parent) of a type, ordered
     * by id, for the bulk export path. Roots-only mirrors `include_variants=off`
     * (masters only). The caller walks pages and `EntityManager::clear()`s
     * between them so a 50k export stays in constant memory; keyset (`id >
     * :afterId`) avoids the O(n²) cost of OFFSET on a large table.
     *
     * @return list<CatalogObject>
     */
    public function findRootObjectsAfter(ObjectType $objectType, Tenant $tenant, ?\Symfony\Component\Uid\Uuid $afterId, int $limit): array
    {
        $qb = $this->createQueryBuilder('o')
            ->where('o.objectType = :ot')
            ->andWhere('o.tenant = :tenant')
            ->andWhere('o.parent IS NULL')
            ->setParameter('ot', $objectType)
            ->setParameter('tenant', $tenant)
            ->orderBy('o.id', 'ASC')
            ->setMaxResults(max(1, $limit));

        if (null !== $afterId) {
            $qb->andWhere('o.id > :afterId')->setParameter('afterId', $afterId->toRfc4122());
        }

        /** @var list<CatalogObject> $rows */
        $rows = $qb->getQuery()->getResult();

        return $rows;
    }

    /**
     * IMP2-2.6 — count root objects of a type via COUNT(*) without hydrating
     * entities (the export progress total needs the count, not the object
     * graph). Roots-only matches {@see self::iterateRootObjectsByType()}.
     */
    public function countRootObjectsByType(ObjectType $objectType, Tenant $tenant): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->where('o.objectType = :ot')
            ->andWhere('o.tenant = :tenant')
            ->andWhere('o.parent IS NULL')
            ->setParameter('ot', $objectType)
            ->setParameter('tenant', $tenant)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<string>
     */
    public function findRootObjectIds(ObjectType $objectType, Tenant $tenant): array
    {
        /** @var list<array{id: string}> $rows */
        $rows = $this->createQueryBuilder('o')
            ->select('o.id')
            ->where('o.objectType = :ot')
            ->andWhere('o.tenant = :tenant')
            ->andWhere('o.parent IS NULL')
            ->setParameter('ot', $objectType)
            ->setParameter('tenant', $tenant)
            ->orderBy('o.id', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): string => $row['id'], $rows);
    }

    /**
     * @param list<string> $idsRfc4122
     *
     * @return list<string>
     */
    public function filterRootObjectIds(array $idsRfc4122, Tenant $tenant): array
    {
        if ([] === $idsRfc4122) {
            return [];
        }

        /** @var list<array{id: string}> $rows */
        $rows = $this->createQueryBuilder('o')
            ->select('o.id')
            ->where('o.id IN (:ids)')
            ->andWhere('o.tenant = :tenant')
            ->andWhere('o.parent IS NULL')
            ->setParameter('ids', $idsRfc4122)
            ->setParameter('tenant', $tenant)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): string => $row['id'], $rows);
    }

    /**
     * @param list<string> $parentIdsRfc4122
     *
     * @return array<string, list<string>>
     */
    public function findChildIdsByParentIds(array $parentIdsRfc4122, Tenant $tenant): array
    {
        if ([] === $parentIdsRfc4122) {
            return [];
        }

        /** @var list<array{id: string, pid: string}> $rows */
        $rows = $this->createQueryBuilder('o')
            ->select('o.id AS id', 'IDENTITY(o.parent) AS pid')
            ->where('o.parent IN (:parents)')
            ->andWhere('o.tenant = :tenant')
            ->setParameter('parents', $parentIdsRfc4122)
            ->setParameter('tenant', $tenant)
            ->orderBy('o.code', 'ASC')
            ->getQuery()
            ->getScalarResult();

        $byParent = [];
        foreach ($rows as $row) {
            $byParent[$row['pid']][] = $row['id'];
        }

        return $byParent;
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
