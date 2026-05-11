<?php

declare(strict_types=1);

namespace App\Import\Infrastructure\Doctrine\Repository;

use App\Import\Domain\Entity\ImportSource;
use App\Import\Domain\Repository\ImportSourceRepositoryInterface;
use App\Shared\Domain\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ImportSource>
 */
class DoctrineImportSourceRepository extends ServiceEntityRepository implements ImportSourceRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImportSource::class);
    }

    public function save(ImportSource $source): void
    {
        $em = $this->getEntityManager();
        $em->persist($source);
        $em->flush();
    }

    public function remove(ImportSource $source): void
    {
        $em = $this->getEntityManager();
        $em->remove($source);
        $em->flush();
    }

    public function findById(Uuid $id): ?ImportSource
    {
        return parent::find($id->toRfc4122());
    }

    public function findByCode(Tenant $tenant, string $code): ?ImportSource
    {
        return $this->findOneBy(['tenant' => $tenant, 'code' => $code]);
    }

    /**
     * @return list<ImportSource>
     */
    public function findByTenant(Tenant $tenant): array
    {
        $qb = $this->createQueryBuilder('s')
            ->where('s.tenant = :tenant')
            ->orderBy('s.createdAt', 'DESC')
            ->setParameter('tenant', $tenant);

        /** @var list<ImportSource> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }
}
