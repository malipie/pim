<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\Repository;

use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\AttributeOption;
use App\Catalog\Domain\Repository\AttributeOptionRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AttributeOption>
 */
class DoctrineAttributeOptionRepository extends ServiceEntityRepository implements AttributeOptionRepositoryInterface
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

    /**
     * @return list<string>
     */
    public function findCodesByAttribute(Attribute $attribute): array
    {
        /** @var list<string> $codes */
        $codes = $this->createQueryBuilder('o')
            ->select('o.code')
            ->andWhere('o.attribute = :attribute')
            ->setParameter('attribute', $attribute)
            ->orderBy('o.position', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        return $codes;
    }

    /**
     * @param list<Attribute> $attributes
     *
     * @return list<AttributeOption>
     */
    public function findByAttributes(array $attributes): array
    {
        if ([] === $attributes) {
            return [];
        }
        /** @var list<AttributeOption> $rows */
        $rows = $this->createQueryBuilder('o')
            ->andWhere('o.attribute IN (:attributes)')
            ->setParameter('attributes', $attributes)
            ->orderBy('IDENTITY(o.attribute)', 'ASC')
            ->addOrderBy('o.position', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    public function findById(\Symfony\Component\Uid\Uuid $id): ?AttributeOption
    {
        return parent::find($id->toRfc4122());
    }

    public function save(AttributeOption $entity): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
    }

    public function remove(AttributeOption $entity): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        $em->flush();
    }
}
