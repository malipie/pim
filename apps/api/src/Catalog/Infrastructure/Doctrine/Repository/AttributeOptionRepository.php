<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\Repository;

use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\AttributeOption;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AttributeOption>
 */
class AttributeOptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AttributeOption::class);
    }

    /**
     * @return list<AttributeOption>
     */
    public function findByAttribute(Attribute $attribute): array
    {
        /** @var list<AttributeOption> $rows */
        $rows = $this->createQueryBuilder('o')
            ->andWhere('o.attribute = :attribute')
            ->setParameter('attribute', $attribute)
            ->orderBy('o.position', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
