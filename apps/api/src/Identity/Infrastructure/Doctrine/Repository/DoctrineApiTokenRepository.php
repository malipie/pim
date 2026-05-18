<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Doctrine\Repository;

use App\Identity\Domain\Entity\ApiToken;
use App\Identity\Domain\Repository\ApiTokenRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ApiToken>
 */
class DoctrineApiTokenRepository extends ServiceEntityRepository implements ApiTokenRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiToken::class);
    }

    public function findById(Uuid $id): ?ApiToken
    {
        return parent::find($id->toRfc4122());
    }

    public function findByHash(string $tokenHash): ?ApiToken
    {
        return $this->findOneBy(['tokenHash' => $tokenHash]);
    }

    /**
     * @return list<ApiToken>
     */
    public function findByUser(Uuid $userId): array
    {
        return array_values($this->findBy(['userId' => $userId->toRfc4122()]));
    }

    /**
     * @return list<ApiToken>
     */
    public function findByTenant(Uuid $tenantId): array
    {
        return array_values($this->findBy(['tenantId' => $tenantId->toRfc4122()]));
    }

    public function save(ApiToken $entity): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
    }

    public function remove(ApiToken $entity): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        $em->flush();
    }
}
