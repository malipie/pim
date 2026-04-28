<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\Repository;

use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\Entity\ObjectTypeAttribute;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ObjectTypeAttribute>
 */
class ObjectTypeAttributeRepository extends ServiceEntityRepository
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

    public function findOne(ObjectType $objectType, Attribute $attribute): ?ObjectTypeAttribute
    {
        return $this->findOneBy(['objectType' => $objectType, 'attribute' => $attribute]);
    }
}
