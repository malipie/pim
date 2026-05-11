<?php

declare(strict_types=1);

namespace App\Import\Infrastructure\Doctrine\Repository;

use App\Import\Domain\Entity\ImportProfile;
use App\Import\Domain\Repository\ImportProfileRepositoryInterface;
use App\Shared\Domain\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ImportProfile>
 */
class DoctrineImportProfileRepository extends ServiceEntityRepository implements ImportProfileRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImportProfile::class);
    }

    public function save(ImportProfile $profile): void
    {
        $em = $this->getEntityManager();
        $em->persist($profile);
        $em->flush();
    }

    public function remove(ImportProfile $profile): void
    {
        $em = $this->getEntityManager();
        $em->remove($profile);
        $em->flush();
    }

    public function findById(Uuid $id): ?ImportProfile
    {
        return parent::find($id->toRfc4122());
    }

    public function findByName(Tenant $tenant, Uuid $userId, string $name): ?ImportProfile
    {
        return $this->findOneBy(['tenant' => $tenant, 'userId' => $userId, 'name' => $name]);
    }

    public function findByCode(Tenant $tenant, Uuid $userId, string $code): ?ImportProfile
    {
        return $this->findOneBy(['tenant' => $tenant, 'userId' => $userId, 'code' => $code]);
    }

    /**
     * @return list<ImportProfile>
     */
    public function findByTenantAndUser(Tenant $tenant, Uuid $userId): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.tenant = :tenant')
            ->andWhere('p.userId = :userId')
            ->orderBy('p.lastUsedAt', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->setParameter('tenant', $tenant)
            ->setParameter('userId', $userId);

        /** @var list<ImportProfile> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }
}
