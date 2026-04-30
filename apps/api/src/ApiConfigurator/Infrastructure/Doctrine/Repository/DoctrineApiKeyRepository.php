<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Infrastructure\Doctrine\Repository;

use App\ApiConfigurator\Domain\Entity\ApiKey;
use App\ApiConfigurator\Domain\Repository\ApiKeyRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ApiKey>
 */
class DoctrineApiKeyRepository extends ServiceEntityRepository implements ApiKeyRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiKey::class);
    }

    public function save(ApiKey $key): void
    {
        $em = $this->getEntityManager();
        $em->persist($key);
        $em->flush();
    }

    public function remove(ApiKey $key): void
    {
        $em = $this->getEntityManager();
        $em->remove($key);
        $em->flush();
    }

    public function findById(Uuid $id): ?ApiKey
    {
        return parent::find($id->toRfc4122());
    }

    public function findByKeyPrefix(string $keyPrefix): ?ApiKey
    {
        return $this->findOneBy(['keyPrefix' => $keyPrefix]);
    }
}
