<?php

declare(strict_types=1);

namespace App\Integration\Generic\Infrastructure\Doctrine\Repository;

use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Entity\RemoteEndpoint;
use App\Integration\Generic\Domain\Enum\RemoteEndpointRole;
use App\Integration\Generic\Domain\Repository\RemoteEndpointRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<RemoteEndpoint>
 */
class DoctrineRemoteEndpointRepository extends ServiceEntityRepository implements RemoteEndpointRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RemoteEndpoint::class);
    }

    public function save(RemoteEndpoint $endpoint): void
    {
        $em = $this->getEntityManager();
        $em->persist($endpoint);
        $em->flush();
    }

    public function remove(RemoteEndpoint $endpoint): void
    {
        $em = $this->getEntityManager();
        $em->remove($endpoint);
        $em->flush();
    }

    public function findById(Uuid $id): ?RemoteEndpoint
    {
        return parent::find($id->toRfc4122());
    }

    /**
     * @return list<RemoteEndpoint>
     */
    public function findByConnection(Connection $connection): array
    {
        $qb = $this->createQueryBuilder('e')
            ->where('e.connection = :connection')
            ->orderBy('e.createdAt', 'ASC')
            ->setParameter('connection', $connection);

        /** @var list<RemoteEndpoint> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    public function findByConnectionAndRole(Connection $connection, RemoteEndpointRole $role): ?RemoteEndpoint
    {
        return $this->findOneBy(['connection' => $connection, 'role' => $role->value]);
    }
}
