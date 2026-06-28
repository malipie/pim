<?php

declare(strict_types=1);

namespace App\Integration\Generic\Infrastructure\Doctrine\Repository;

use App\Integration\Generic\Domain\Entity\SyncRun;
use App\Integration\Generic\Domain\Entity\SyncRunLog;
use App\Integration\Generic\Domain\Repository\SyncRunLogRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<SyncRunLog>
 */
class DoctrineSyncRunLogRepository extends ServiceEntityRepository implements SyncRunLogRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SyncRunLog::class);
    }

    public function save(SyncRunLog $log): void
    {
        $em = $this->getEntityManager();
        $em->persist($log);
        $em->flush();
    }

    public function findById(Uuid $id): ?SyncRunLog
    {
        return parent::find($id->toRfc4122());
    }

    /**
     * @return list<SyncRunLog>
     */
    public function findByRun(SyncRun $run): array
    {
        $qb = $this->createQueryBuilder('l')
            ->where('l.run = :run')
            ->orderBy('l.createdAt', 'ASC')
            ->setParameter('run', $run);

        /** @var list<SyncRunLog> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }
}
