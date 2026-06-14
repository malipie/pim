<?php

declare(strict_types=1);

namespace App\Import\Infrastructure\Doctrine\Repository;

use App\Import\Domain\Entity\StagedFile;
use App\Import\Domain\Repository\StagedFileRepositoryInterface;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<StagedFile>
 */
class DoctrineStagedFileRepository extends ServiceEntityRepository implements StagedFileRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StagedFile::class);
    }

    public function save(StagedFile $stagedFile): void
    {
        $em = $this->getEntityManager();
        $em->persist($stagedFile);
        $em->flush();
    }

    public function remove(StagedFile $stagedFile): void
    {
        $em = $this->getEntityManager();
        $em->remove($stagedFile);
        $em->flush();
    }

    public function findOwned(Uuid $id, Tenant $tenant, Uuid $userId): ?StagedFile
    {
        /** @var StagedFile|null $result */
        $result = $this->createQueryBuilder('s')
            ->where('s.id = :id')
            ->andWhere('s.tenant = :tenant')
            ->andWhere('s.userId = :userId')
            ->setParameter('id', $id->toRfc4122())
            ->setParameter('tenant', $tenant)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }

    /**
     * @return list<StagedFile>
     */
    public function findExpired(Tenant $tenant, DateTimeImmutable $createdBefore, int $limit = 500): array
    {
        $qb = $this->createQueryBuilder('s')
            ->where('s.tenant = :tenant')
            ->andWhere('s.createdAt < :cutoff')
            ->orderBy('s.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->setParameter('tenant', $tenant)
            ->setParameter('cutoff', $createdBefore);

        /** @var list<StagedFile> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }
}
