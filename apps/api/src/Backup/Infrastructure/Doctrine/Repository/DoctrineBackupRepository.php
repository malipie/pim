<?php

declare(strict_types=1);

namespace App\Backup\Infrastructure\Doctrine\Repository;

use App\Backup\Domain\Entity\Backup;
use App\Backup\Domain\Repository\BackupRepositoryInterface;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Backup>
 */
class DoctrineBackupRepository extends ServiceEntityRepository implements BackupRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Backup::class);
    }

    public function save(Backup $backup): void
    {
        $em = $this->getEntityManager();
        $em->persist($backup);
        $em->flush();
    }

    public function findById(Uuid $id): ?Backup
    {
        return parent::find($id->toRfc4122());
    }

    public function countSince(Tenant $tenant, DateTimeImmutable $since): int
    {
        $qb = $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.tenant = :tenant')
            ->andWhere('b.startedAt >= :since')
            ->setParameter('tenant', $tenant)
            ->setParameter('since', $since);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
