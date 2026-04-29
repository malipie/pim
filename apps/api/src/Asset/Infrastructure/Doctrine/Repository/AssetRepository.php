<?php

declare(strict_types=1);

namespace App\Asset\Infrastructure\Doctrine\Repository;

use App\Asset\Domain\Entity\Asset;
use App\Shared\Domain\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Asset>
 */
class AssetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Asset::class);
    }

    public function findByCode(string $code, Tenant $tenant): ?Asset
    {
        return $this->findOneBy(['code' => $code, 'tenant' => $tenant]);
    }
}
