<?php

declare(strict_types=1);

namespace App\Integration\Generic\Infrastructure\Doctrine\Repository;

use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Entity\SyncBinding;
use App\Integration\Generic\Domain\Repository\SyncBindingRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<SyncBinding>
 */
class DoctrineSyncBindingRepository extends ServiceEntityRepository implements SyncBindingRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SyncBinding::class);
    }

    public function save(SyncBinding $binding): void
    {
        $em = $this->getEntityManager();
        $em->persist($binding);
        $em->flush();
    }

    public function remove(SyncBinding $binding): void
    {
        $em = $this->getEntityManager();
        $em->remove($binding);
        $em->flush();
    }

    public function findById(Uuid $id): ?SyncBinding
    {
        return parent::find($id->toRfc4122());
    }

    /**
     * @return list<SyncBinding>
     */
    public function findByConnection(Connection $connection): array
    {
        $qb = $this->createQueryBuilder('b')
            ->where('b.connection = :connection')
            ->orderBy('b.createdAt', 'ASC')
            ->setParameter('connection', $connection);

        /** @var list<SyncBinding> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * @return list<SyncBinding>
     */
    public function findEnabled(): array
    {
        $qb = $this->createQueryBuilder('b')
            ->where('b.enabled = true')
            ->orderBy('b.createdAt', 'ASC');

        /** @var list<SyncBinding> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }
}
