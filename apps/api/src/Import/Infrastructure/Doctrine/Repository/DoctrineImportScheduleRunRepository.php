<?php

declare(strict_types=1);

namespace App\Import\Infrastructure\Doctrine\Repository;

use App\Import\Domain\Entity\ImportScheduleRun;
use App\Import\Domain\Repository\ImportScheduleRunRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ImportScheduleRun>
 */
class DoctrineImportScheduleRunRepository extends ServiceEntityRepository implements ImportScheduleRunRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImportScheduleRun::class);
    }

    public function save(ImportScheduleRun $run): void
    {
        $em = $this->getEntityManager();
        $em->persist($run);
        $em->flush();
    }

    /**
     * @return list<ImportScheduleRun>
     */
    public function findByScheduleId(Uuid $scheduleId, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('r')
            ->where('r.scheduleId = :sid')
            ->orderBy('r.triggeredAt', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('sid', $scheduleId);

        /** @var list<ImportScheduleRun> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }
}
