<?php

declare(strict_types=1);

namespace App\Asset\Infrastructure\Doctrine\Repository;

use App\Asset\Domain\Entity\Asset;
use App\Asset\Domain\Entity\AssetVariant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AssetVariant>
 */
class AssetVariantRepository extends ServiceEntityRepository
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
}
