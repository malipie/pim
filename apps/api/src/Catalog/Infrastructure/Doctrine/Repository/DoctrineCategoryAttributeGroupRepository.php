<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\Repository;

use App\Catalog\Domain\Entity\AttributeGroup;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\CategoryAttributeGroup;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\Repository\CategoryAttributeGroupRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CategoryAttributeGroup>
 */
final class DoctrineCategoryAttributeGroupRepository extends ServiceEntityRepository implements CategoryAttributeGroupRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CategoryAttributeGroup::class);
    }

    public function findOne(
        CatalogObject $category,
        ObjectType $targetObjectType,
        AttributeGroup $attributeGroup,
    ): ?CategoryAttributeGroup {
        /** @var CategoryAttributeGroup|null $row */
        $row = $this->createQueryBuilder('j')
            ->andWhere('j.categoryObjectId = :categoryId')
            ->andWhere('j.targetObjectType = :type')
            ->andWhere('j.attributeGroup = :group')
            ->setParameter('categoryId', $category->getId(), 'uuid')
            ->setParameter('type', $targetObjectType)
            ->setParameter('group', $attributeGroup)
            ->getQuery()
            ->getOneOrNullResult();

        return $row;
    }

    public function findByCategoryAndTarget(
        CatalogObject $category,
        ObjectType $targetObjectType,
    ): array {
        /** @var list<CategoryAttributeGroup> $rows */
        $rows = $this->createQueryBuilder('j')
            ->innerJoin('j.attributeGroup', 'g')
            ->addSelect('g')
            ->andWhere('j.categoryObjectId = :categoryId')
            ->andWhere('j.targetObjectType = :type')
            ->orderBy('j.position', 'ASC')
            ->addOrderBy('g.code', 'ASC')
            ->setParameter('categoryId', $category->getId(), 'uuid')
            ->setParameter('type', $targetObjectType)
            ->getQuery()
            ->getResult();

        return $rows;
    }

    public function maxPosition(CatalogObject $category, ObjectType $targetObjectType): ?int
    {
        $value = $this->createQueryBuilder('j')
            ->select('MAX(j.position)')
            ->andWhere('j.categoryObjectId = :categoryId')
            ->andWhere('j.targetObjectType = :type')
            ->setParameter('categoryId', $category->getId(), 'uuid')
            ->setParameter('type', $targetObjectType)
            ->getQuery()
            ->getSingleScalarResult();

        if (null === $value) {
            return null;
        }

        return (int) $value;
    }

    public function save(CategoryAttributeGroup $junction): void
    {
        $em = $this->getEntityManager();
        $em->persist($junction);
        $em->flush();
    }

    public function remove(CategoryAttributeGroup $junction): void
    {
        $em = $this->getEntityManager();
        $em->remove($junction);
        $em->flush();
    }
}
