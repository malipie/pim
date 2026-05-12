<?php

declare(strict_types=1);

namespace App\Import\Infrastructure\Doctrine\Repository;

use App\Import\Domain\Entity\ImportSchedule;
use App\Import\Domain\Repository\ImportScheduleRepositoryInterface;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ImportSchedule>
 */
class DoctrineImportScheduleRepository extends ServiceEntityRepository implements ImportScheduleRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImportSchedule::class);
    }

    public function save(ImportSchedule $schedule): void
    {
        $em = $this->getEntityManager();
        $em->persist($schedule);
        $em->flush();
    }

    public function remove(ImportSchedule $schedule): void
    {
        $em = $this->getEntityManager();
        $em->remove($schedule);
        $em->flush();
    }

    public function findById(Uuid $id): ?ImportSchedule
    {
        return parent::find($id->toRfc4122());
    }

    public function findByCode(Tenant $tenant, string $code): ?ImportSchedule
    {
        return $this->findOneBy(['tenant' => $tenant, 'code' => $code]);
    }

    /**
     * @return list<ImportSchedule>
     */
    public function findByTenant(Tenant $tenant): array
    {
        $qb = $this->createQueryBuilder('s')
            ->where('s.tenant = :tenant')
            ->orderBy('s.createdAt', 'DESC')
            ->setParameter('tenant', $tenant);

        /** @var list<ImportSchedule> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * @return list<ImportSchedule>
     */
    public function findUpcoming(Tenant $tenant, DateTimeImmutable $until): array
    {
        $qb = $this->createQueryBuilder('s')
            ->where('s.tenant = :tenant')
            ->andWhere('s.enabled = true')
            ->andWhere('s.nextRun IS NOT NULL')
            ->andWhere('s.nextRun <= :until')
            ->orderBy('s.nextRun', 'ASC')
            ->setParameter('tenant', $tenant)
            ->setParameter('until', $until);

        /** @var list<ImportSchedule> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }
}
