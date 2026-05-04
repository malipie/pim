<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\Repository;

use App\Catalog\Domain\Entity\MenuConfiguration;
use App\Catalog\Domain\Repository\MenuConfigurationRepositoryInterface;
use App\Shared\Domain\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<MenuConfiguration>
 */
class DoctrineMenuConfigurationRepository extends ServiceEntityRepository implements MenuConfigurationRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MenuConfiguration::class);
    }

    public function findByTenant(Tenant $tenant): ?MenuConfiguration
    {
        return $this->findOneBy(['tenant' => $tenant]);
    }
}
