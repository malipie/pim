<?php

declare(strict_types=1);

namespace App\Import\Infrastructure\Doctrine\Repository;

use App\Import\Domain\Entity\ImportLog;
use App\Import\Domain\Entity\ImportSession;
use App\Import\Domain\Enum\ImportLogLevel;
use App\Import\Domain\Repository\ImportLogRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Generator;

/**
 * @extends ServiceEntityRepository<ImportLog>
 */
class DoctrineImportLogRepository extends ServiceEntityRepository implements ImportLogRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImportLog::class);
    }

    public function save(ImportLog $log): void
    {
        $em = $this->getEntityManager();
        $em->persist($log);
        $em->flush();
    }

    /**
     * @param list<ImportLogLevel> $levels
     *
     * @return list<ImportLog>
     */
    public function findBySession(ImportSession $session, array $levels = [], int $limit = 1000): array
    {
        $qb = $this->createQueryBuilder('l')
            ->where('l.importSession = :session')
            ->orderBy('l.rowNumber', 'ASC')
            ->setMaxResults($limit)
            ->setParameter('session', $session);

        if ([] !== $levels) {
            $qb->andWhere('l.level IN (:levels)')
                ->setParameter('levels', array_map(static fn (ImportLogLevel $level): string => $level->value, $levels));
        }

        /** @var list<ImportLog> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * @param list<ImportLogLevel> $levels
     *
     * @return Generator<int, ImportLog>
     */
    public function iterateBySession(ImportSession $session, array $levels = []): Generator
    {
        $qb = $this->createQueryBuilder('l')
            ->where('l.importSession = :session')
            ->orderBy('l.rowNumber', 'ASC')
            ->setParameter('session', $session);

        if ([] !== $levels) {
            $qb->andWhere('l.level IN (:levels)')
                ->setParameter('levels', array_map(static fn (ImportLogLevel $level): string => $level->value, $levels));
        }

        // Memory-flat: hydrate one row at a time and detach every 500 so the
        // identity map cannot accumulate the whole (up to 200k-row) log set.
        $em = $this->getEntityManager();
        $seen = 0;
        foreach ($qb->getQuery()->toIterable() as $log) {
            yield $log;
            if (0 === ++$seen % 500) {
                $em->clear();
            }
        }
    }

    public function countBySession(ImportSession $session, ?ImportLogLevel $level = null): int
    {
        $qb = $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->where('l.importSession = :session')
            ->setParameter('session', $session);

        if (null !== $level) {
            $qb->andWhere('l.level = :level')->setParameter('level', $level->value);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
