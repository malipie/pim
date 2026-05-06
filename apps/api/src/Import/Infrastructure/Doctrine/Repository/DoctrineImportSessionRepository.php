<?php

declare(strict_types=1);

namespace App\Import\Infrastructure\Doctrine\Repository;

use App\Import\Domain\Entity\ImportSession;
use App\Import\Domain\Repository\ImportSessionRepositoryInterface;
use App\Shared\Domain\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ImportSession>
 */
class DoctrineImportSessionRepository extends ServiceEntityRepository implements ImportSessionRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImportSession::class);
    }

    public function save(ImportSession $session): void
    {
        $em = $this->getEntityManager();
        $em->persist($session);
        $em->flush();
    }

    public function findById(Uuid $id): ?ImportSession
    {
        return parent::find($id->toRfc4122());
    }

    /**
     * @return list<ImportSession>
     */
    public function findByTenantAndUser(Tenant $tenant, Uuid $userId, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('s')
            ->where('s.tenant = :tenant')
            ->andWhere('s.userId = :userId')
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('tenant', $tenant)
            ->setParameter('userId', $userId);

        /** @var list<ImportSession> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }
}
