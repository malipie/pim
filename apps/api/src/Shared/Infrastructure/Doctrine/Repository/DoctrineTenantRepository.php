<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Repository;

use App\Shared\Domain\Repository\TenantRepositoryInterface;
use App\Shared\Domain\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Tenant>
 */
class DoctrineTenantRepository extends ServiceEntityRepository implements TenantRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tenant::class);
    }

    public function findById(Uuid $id): ?Tenant
    {
        return parent::find($id->toRfc4122());
    }

    public function findByCode(string $code): ?Tenant
    {
        return $this->findOneBy(['code' => $code]);
    }

    public function save(Tenant $tenant): void
    {
        $em = $this->getEntityManager();
        $em->persist($tenant);
        $em->flush();
    }

    public function remove(Tenant $tenant): void
    {
        $em = $this->getEntityManager();
        $em->remove($tenant);
        $em->flush();
    }

    public function findAllOrderedByCode(): array
    {
        /** @var list<Tenant> $result */
        $result = $this->createQueryBuilder('t')
            ->orderBy('t.code', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
