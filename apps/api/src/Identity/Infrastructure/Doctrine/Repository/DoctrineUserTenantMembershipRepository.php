<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Doctrine\Repository;

use App\Identity\Domain\Entity\UserTenantMembership;
use App\Identity\Domain\Repository\UserTenantMembershipRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<UserTenantMembership>
 */
class DoctrineUserTenantMembershipRepository extends ServiceEntityRepository implements UserTenantMembershipRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserTenantMembership::class);
    }

    public function findById(Uuid $id): ?UserTenantMembership
    {
        return parent::find($id->toRfc4122());
    }

    /**
     * @return list<UserTenantMembership>
     */
    public function findByUser(Uuid $userId): array
    {
        return $this->findBy(['userId' => $userId->toRfc4122()]);
    }

    /**
     * @return list<UserTenantMembership>
     */
    public function findByTenant(Uuid $tenantId): array
    {
        return $this->findBy(['tenantId' => $tenantId->toRfc4122()]);
    }

    public function findByUserAndTenant(Uuid $userId, Uuid $tenantId): ?UserTenantMembership
    {
        return $this->findOneBy([
            'userId' => $userId->toRfc4122(),
            'tenantId' => $tenantId->toRfc4122(),
        ]);
    }

    public function save(UserTenantMembership $entity): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
    }

    public function remove(UserTenantMembership $entity): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        $em->flush();
    }
}
