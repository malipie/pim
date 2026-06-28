<?php

declare(strict_types=1);

namespace App\Integration\Generic\Infrastructure\Doctrine\Repository;

use App\Integration\Generic\Domain\Entity\RemoteEndpoint;
use App\Integration\Generic\Domain\Entity\RemoteField;
use App\Integration\Generic\Domain\Repository\RemoteFieldRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<RemoteField>
 */
class DoctrineRemoteFieldRepository extends ServiceEntityRepository implements RemoteFieldRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RemoteField::class);
    }

    public function save(RemoteField $field): void
    {
        $em = $this->getEntityManager();
        $em->persist($field);
        $em->flush();
    }

    public function remove(RemoteField $field): void
    {
        $em = $this->getEntityManager();
        $em->remove($field);
        $em->flush();
    }

    public function findById(Uuid $id): ?RemoteField
    {
        return parent::find($id->toRfc4122());
    }

    /**
     * @return list<RemoteField>
     */
    public function findByEndpoint(RemoteEndpoint $endpoint): array
    {
        $qb = $this->createQueryBuilder('f')
            ->where('f.endpoint = :endpoint')
            ->orderBy('f.path', 'ASC')
            ->setParameter('endpoint', $endpoint);

        /** @var list<RemoteField> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }
}
