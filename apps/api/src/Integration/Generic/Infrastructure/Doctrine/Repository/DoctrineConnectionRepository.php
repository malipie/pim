<?php

declare(strict_types=1);

namespace App\Integration\Generic\Infrastructure\Doctrine\Repository;

use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Repository\ConnectionRepositoryInterface;
use App\Shared\Domain\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Connection>
 */
class DoctrineConnectionRepository extends ServiceEntityRepository implements ConnectionRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Connection::class);
    }

    public function save(Connection $connection): void
    {
        $em = $this->getEntityManager();
        $em->persist($connection);
        $em->flush();
    }

    public function remove(Connection $connection): void
    {
        $em = $this->getEntityManager();
        $em->remove($connection);
        $em->flush();
    }

    public function findById(Uuid $id): ?Connection
    {
        return parent::find($id->toRfc4122());
    }

    public function findByCode(Tenant $tenant, string $code): ?Connection
    {
        return $this->findOneBy(['tenant' => $tenant, 'code' => $code]);
    }

    /**
     * @return list<Connection>
     */
    public function findByTenant(Tenant $tenant): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.tenant = :tenant')
            ->orderBy('c.createdAt', 'DESC')
            ->setParameter('tenant', $tenant);

        /** @var list<Connection> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }
}
