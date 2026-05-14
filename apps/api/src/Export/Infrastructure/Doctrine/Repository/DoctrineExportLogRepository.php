<?php

declare(strict_types=1);

namespace App\Export\Infrastructure\Doctrine\Repository;

use App\Export\Domain\Entity\ExportLog;
use App\Export\Domain\Entity\ExportSession;
use App\Export\Domain\Repository\ExportLogRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExportLog>
 */
class DoctrineExportLogRepository extends ServiceEntityRepository implements ExportLogRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExportLog::class);
    }

    public function save(ExportLog $log): void
    {
        $em = $this->getEntityManager();
        $em->persist($log);
        $em->flush();
    }

    /**
     * @return list<ExportLog>
     */
    public function findRecentForSession(ExportSession $session, int $limit = 100): array
    {
        $qb = $this->createQueryBuilder('l')
            ->where('l.exportSession = :session')
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('session', $session);

        /** @var list<ExportLog> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }
}
