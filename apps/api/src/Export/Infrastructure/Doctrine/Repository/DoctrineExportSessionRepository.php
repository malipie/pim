<?php

declare(strict_types=1);

namespace App\Export\Infrastructure\Doctrine\Repository;

use App\Export\Domain\Entity\ExportSession;
use App\Export\Domain\Enum\ExportStatus;
use App\Export\Domain\Repository\ExportSessionRepositoryInterface;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ExportSession>
 */
class DoctrineExportSessionRepository extends ServiceEntityRepository implements ExportSessionRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExportSession::class);
    }

    public function save(ExportSession $session): void
    {
        $em = $this->getEntityManager();
        $em->persist($session);
        $em->flush();
    }

    public function remove(ExportSession $session): void
    {
        $em = $this->getEntityManager();
        $em->remove($session);
        $em->flush();
    }

    public function findById(Uuid $id): ?ExportSession
    {
        return parent::find($id->toRfc4122());
    }

    /**
     * @return list<ExportSession>
     */
    public function findByTenantAndUser(Tenant $tenant, Uuid $userId, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('s')
            ->where('s.tenant = :tenant')
            ->andWhere('s.userId = :userId')
            ->orderBy('s.startedAt', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('tenant', $tenant)
            ->setParameter('userId', $userId);

        /** @var list<ExportSession> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * @return list<ExportSession>
     */
    public function findOlderThan(Tenant $tenant, DateTimeImmutable $olderThan, int $limit = 500): array
    {
        $qb = $this->createQueryBuilder('s')
            ->where('s.tenant = :tenant')
            ->andWhere('s.startedAt < :cutoff')
            ->orderBy('s.startedAt', 'ASC')
            ->setMaxResults($limit)
            ->setParameter('tenant', $tenant)
            ->setParameter('cutoff', $olderThan);

        /** @var list<ExportSession> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    public function countActiveForTenant(Tenant $tenant): int
    {
        $qb = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.tenant = :tenant')
            ->andWhere('s.status IN (:active)')
            ->setParameter('tenant', $tenant)
            ->setParameter('active', [ExportStatus::Pending->value, ExportStatus::Running->value]);

        /** @var int|string $count */
        $count = $qb->getQuery()->getSingleScalarResult();

        return (int) $count;
    }
}
