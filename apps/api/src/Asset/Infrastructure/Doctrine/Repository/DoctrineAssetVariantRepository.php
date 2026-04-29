<?php

declare(strict_types=1);

namespace App\Asset\Infrastructure\Doctrine\Repository;

use App\Asset\Domain\Entity\Asset;
use App\Asset\Domain\Entity\AssetVariant;
use App\Asset\Domain\Repository\AssetVariantRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AssetVariant>
 */
class DoctrineAssetVariantRepository extends ServiceEntityRepository implements AssetVariantRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AssetVariant::class);
    }

    /**
     * @return list<AssetVariant>
     */
    public function findByAsset(Asset $asset): array
    {
        /** @var list<AssetVariant> $rows */
        $rows = $this->findBy(['asset' => $asset]);

        return $rows;
    }

    public function findById(\Symfony\Component\Uid\Uuid $id): ?AssetVariant
    {
        return parent::find($id->toRfc4122());
    }

    public function save(AssetVariant $entity): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
    }

    public function remove(AssetVariant $entity): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        $em->flush();
    }
}
