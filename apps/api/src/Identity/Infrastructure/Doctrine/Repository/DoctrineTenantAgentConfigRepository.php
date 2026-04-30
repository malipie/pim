<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Doctrine\Repository;

use App\Identity\Domain\Entity\TenantAgentConfig;
use App\Identity\Domain\Repository\TenantAgentConfigRepositoryInterface;
use App\Shared\Domain\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TenantAgentConfig>
 */
class DoctrineTenantAgentConfigRepository extends ServiceEntityRepository implements TenantAgentConfigRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TenantAgentConfig::class);
    }

    public function save(TenantAgentConfig $config): void
    {
        $em = $this->getEntityManager();
        $em->persist($config);
        $em->flush();
    }

    public function remove(TenantAgentConfig $config): void
    {
        $em = $this->getEntityManager();
        $em->remove($config);
        $em->flush();
    }

    public function findForTenant(Tenant $tenant): ?TenantAgentConfig
    {
        // The TenantFilter narrows the row set to the active tenant, so
        // findOneBy without an explicit tenant arg still returns at most
        // one row — the unique index `(tenant_id)` from the migration
        // guarantees that.
        return $this->findOneBy(['tenant' => $tenant]);
    }
}
