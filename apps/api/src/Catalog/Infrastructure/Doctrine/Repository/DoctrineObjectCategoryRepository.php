<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\Repository;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectCategory;
use App\Catalog\Domain\Repository\ObjectCategoryRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ObjectCategory>
 */
final class DoctrineObjectCategoryRepository extends ServiceEntityRepository implements ObjectCategoryRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ObjectCategory::class);
    }

    public function findByProduct(CatalogObject $product): array
    {
        /** @var list<ObjectCategory> $rows */
        $rows = $this->createQueryBuilder('a')
            ->innerJoin('a.category', 'c')
            ->addSelect('c')
            ->andWhere('a.product = :product')
            ->orderBy('a.position', 'ASC')
            ->addOrderBy('a.createdAt', 'ASC')
            ->setParameter('product', $product)
            ->getQuery()
            ->getResult();

        return $rows;
    }

    public function findByCategory(CatalogObject $category): array
    {
        /** @var list<ObjectCategory> $rows */
        $rows = $this->createQueryBuilder('a')
            ->innerJoin('a.product', 'p')
            ->addSelect('p')
            ->andWhere('a.category = :category')
            ->orderBy('a.position', 'ASC')
            ->addOrderBy('a.createdAt', 'ASC')
            ->setParameter('category', $category)
            ->getQuery()
            ->getResult();

        return $rows;
    }

    public function findOne(CatalogObject $product, CatalogObject $category): ?ObjectCategory
    {
        /** @var ObjectCategory|null $row */
        $row = $this->createQueryBuilder('a')
            ->andWhere('a.product = :product')
            ->andWhere('a.category = :category')
            ->setParameter('product', $product)
            ->setParameter('category', $category)
            ->getQuery()
            ->getOneOrNullResult();

        return $row;
    }

    public function findPrimary(CatalogObject $product): ?ObjectCategory
    {
        /** @var ObjectCategory|null $row */
        $row = $this->createQueryBuilder('a')
            ->innerJoin('a.category', 'c')
            ->addSelect('c')
            ->andWhere('a.product = :product')
            ->andWhere('a.isPrimary = true')
            ->setParameter('product', $product)
            ->getQuery()
            ->getOneOrNullResult();

        return $row;
    }

    public function replaceForProduct(CatalogObject $product, array $categoryIds, ?Uuid $primaryId): void
    {
        if ([] === $categoryIds && null !== $primaryId) {
            throw new InvalidArgumentException('primary id must be null when categoryIds is empty');
        }

        if (null !== $primaryId && !$this->containsUuid($categoryIds, $primaryId)) {
            throw new InvalidArgumentException('primary id must be present in categoryIds');
        }

        $em = $this->getEntityManager();

        $em->wrapInTransaction(function () use ($em, $product, $categoryIds, $primaryId): void {
            // Wipe existing assignments via the ORM so the Identity Map is
            // updated alongside the row removal — a DQL DELETE would leave
            // stale managed entities behind and the new persist() calls
            // below would collide with the same composite PKs.
            foreach ($this->findByProduct($product) as $existing) {
                $em->remove($existing);
            }
            $em->flush();

            $position = 0;
            foreach ($categoryIds as $categoryId) {
                $category = $em->getReference(CatalogObject::class, $categoryId->toRfc4122());
                if (null === $category) {
                    throw new InvalidArgumentException(sprintf('Category %s not found while replacing assignments.', $categoryId->toRfc4122()));
                }
                $isPrimary = null !== $primaryId && $primaryId->equals($categoryId);
                $assignment = new ObjectCategory(
                    product: $product,
                    category: $category,
                    isPrimary: $isPrimary,
                    position: $position++,
                );
                $em->persist($assignment);
            }

            $em->flush();
        });
    }

    public function save(ObjectCategory $assignment): void
    {
        $em = $this->getEntityManager();
        $em->persist($assignment);
        $em->flush();
    }

    public function remove(ObjectCategory $assignment): void
    {
        $em = $this->getEntityManager();
        $em->remove($assignment);
        $em->flush();
    }

    /**
     * @param list<Uuid> $haystack
     */
    private function containsUuid(array $haystack, Uuid $needle): bool
    {
        foreach ($haystack as $candidate) {
            if ($candidate->equals($needle)) {
                return true;
            }
        }

        return false;
    }
}
