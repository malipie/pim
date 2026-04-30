<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Infrastructure\Doctrine\Repository;

use App\ApiConfigurator\Domain\Entity\ApiProfile;
use App\ApiConfigurator\Domain\Repository\ApiProfileRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ApiProfile>
 */
class DoctrineApiProfileRepository extends ServiceEntityRepository implements ApiProfileRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiProfile::class);
    }

    public function save(ApiProfile $profile): void
    {
        $em = $this->getEntityManager();
        $em->persist($profile);
        $em->flush();
    }

    public function remove(ApiProfile $profile): void
    {
        $em = $this->getEntityManager();
        $em->remove($profile);
        $em->flush();
    }

    public function findById(Uuid $id): ?ApiProfile
    {
        return parent::find($id->toRfc4122());
    }

    public function findByCode(string $code): ?ApiProfile
    {
        // The TenantFilter narrows the row set to the current tenant; the
        // unique index `(tenant_id, code)` from the migration guarantees
        // at most one row.
        return $this->findOneBy(['code' => $code]);
    }
}
