<?php

declare(strict_types=1);

namespace App\Integration\Generic\Infrastructure\Doctrine\Repository;

use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Entity\FieldMapping;
use App\Integration\Generic\Domain\Repository\FieldMappingRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<FieldMapping>
 */
class DoctrineFieldMappingRepository extends ServiceEntityRepository implements FieldMappingRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FieldMapping::class);
    }

    public function save(FieldMapping $mapping): void
    {
        $em = $this->getEntityManager();
        $em->persist($mapping);
        $em->flush();
    }

    public function remove(FieldMapping $mapping): void
    {
        $em = $this->getEntityManager();
        $em->remove($mapping);
        $em->flush();
    }

    public function findById(Uuid $id): ?FieldMapping
    {
        return parent::find($id->toRfc4122());
    }

    /**
     * @return list<FieldMapping>
     */
    public function findByConnection(Connection $connection): array
    {
        $qb = $this->createQueryBuilder('m')
            ->where('m.connection = :connection')
            ->orderBy('m.pimTarget', 'ASC')
            ->setParameter('connection', $connection);

        /** @var list<FieldMapping> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    public function findByConnectionAndTarget(Connection $connection, string $pimTarget): ?FieldMapping
    {
        return $this->findOneBy(['connection' => $connection, 'pimTarget' => $pimTarget]);
    }
}
