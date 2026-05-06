<?php

declare(strict_types=1);

namespace App\Asset\Infrastructure\Doctrine\Repository;

use App\Asset\Domain\Entity\Asset;
use App\Asset\Domain\Repository\AssetRepositoryInterface;
use App\Shared\Domain\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Asset>
 */
class DoctrineAssetRepository extends ServiceEntityRepository implements AssetRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Asset::class);
    }

    public function findByCode(string $code, Tenant $tenant): ?Asset
    {
        return $this->findOneBy(['code' => $code, 'tenant' => $tenant]);
    }

    public function findByContentHash(string $contentHash, Tenant $tenant): ?Asset
    {
        return $this->findOneBy(['contentHash' => $contentHash, 'tenant' => $tenant]);
    }

    public function findByObjectId(\Symfony\Component\Uid\Uuid $objectId): ?Asset
    {
        return $this->findOneBy(['objectId' => $objectId->toRfc4122()]);
    }

    public function findById(\Symfony\Component\Uid\Uuid $id): ?Asset
    {
        return parent::find($id->toRfc4122());
    }

    public function save(Asset $entity): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
    }

    public function remove(Asset $entity): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        $em->flush();
    }
}
