<?php

declare(strict_types=1);

namespace App\Export\Infrastructure\Doctrine\Repository;

use App\Export\Domain\Entity\ExportProfile;
use App\Export\Domain\Repository\ExportProfileRepositoryInterface;
use App\Shared\Domain\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ExportProfile>
 */
class DoctrineExportProfileRepository extends ServiceEntityRepository implements ExportProfileRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExportProfile::class);
    }

    public function save(ExportProfile $profile): void
    {
        $em = $this->getEntityManager();
        $em->persist($profile);
        $em->flush();
    }

    public function remove(ExportProfile $profile): void
    {
        $em = $this->getEntityManager();
        $em->remove($profile);
        $em->flush();
    }

    public function findById(Uuid $id): ?ExportProfile
    {
        return parent::find($id->toRfc4122());
    }

    /**
     * @return list<ExportProfile>
     */
    public function findByTenantAndUser(Tenant $tenant, Uuid $userId): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.tenant = :tenant')
            ->andWhere('p.userId = :userId')
            ->orderBy('p.name', 'ASC')
            ->setParameter('tenant', $tenant)
            ->setParameter('userId', $userId);

        /** @var list<ExportProfile> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    public function findByTenantUserAndName(Tenant $tenant, Uuid $userId, string $name): ?ExportProfile
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.tenant = :tenant')
            ->andWhere('p.userId = :userId')
            ->andWhere('p.name = :name')
            ->setMaxResults(1)
            ->setParameter('tenant', $tenant)
            ->setParameter('userId', $userId)
            ->setParameter('name', $name);

        /** @var ExportProfile|null $result */
        $result = $qb->getQuery()->getOneOrNullResult();

        return $result;
    }
}
