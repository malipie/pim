<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\Repository;

use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\Entity\ObjectTypeAttribute;
use App\Catalog\Domain\Repository\ObjectTypeAttributeRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ObjectTypeAttribute>
 */
class DoctrineObjectTypeAttributeRepository extends ServiceEntityRepository implements ObjectTypeAttributeRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ObjectTypeAttribute::class);
    }

    /**
     * @return list<ObjectTypeAttribute>
     */
    public function findByObjectType(ObjectType $objectType): array
    {
        /** @var list<ObjectTypeAttribute> $rows */
        $rows = $this->createQueryBuilder('ota')
            ->andWhere('ota.objectType = :objectType')
            ->setParameter('objectType', $objectType)
            ->orderBy('ota.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * @return list<ObjectTypeAttribute>
     */
    public function findByAttribute(Attribute $attribute): array
    {
        /** @var list<ObjectTypeAttribute> $rows */
        $rows = $this->createQueryBuilder('ota')
            ->andWhere('ota.attribute = :attribute')
            ->setParameter('attribute', $attribute)
            ->getQuery()
            ->getResult();

        return $rows;
    }

    public function findOne(ObjectType $objectType, Attribute $attribute): ?ObjectTypeAttribute
    {
        return $this->findOneBy(['objectType' => $objectType, 'attribute' => $attribute]);
    }

    public function save(ObjectTypeAttribute $entity): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
    }

    public function remove(ObjectTypeAttribute $entity): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        $em->flush();
    }
}
