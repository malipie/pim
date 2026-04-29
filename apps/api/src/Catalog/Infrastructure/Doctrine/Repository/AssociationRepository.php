<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\Repository;

use App\Catalog\Domain\Entity\Association;
use App\Catalog\Domain\Entity\AssociationType;
use App\Catalog\Domain\Entity\CatalogObject;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Association>
 */
class AssociationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Association::class);
    }

    /**
     * @return list<Association>
     */
    public function findAssociations(CatalogObject $source, ?AssociationType $type = null): array
    {
        $criteria = ['source' => $source];
        if (null !== $type) {
            $criteria['type'] = $type;
        }

        /** @var list<Association> $rows */
        $rows = $this->createQueryBuilder('a')
            ->andWhere('a.source = :source')
            ->setParameter('source', $source)
            ->orderBy('a.position', 'ASC')
            ->getQuery()
            ->getResult();

        if (null === $type) {
            return $rows;
        }

        // Filter post-fetch when type given — keeps the SQL simple and
        // mirrors the way the admin UI lists per-type sections.
        return array_values(array_filter(
            $rows,
            static fn (Association $row): bool => $row->getType() === $type,
        ));
    }

    public function findOne(
        CatalogObject $source,
        CatalogObject $target,
        AssociationType $type,
    ): ?Association {
        return $this->findOneBy([
            'source' => $source,
            'target' => $target,
            'type' => $type,
        ]);
    }
}
