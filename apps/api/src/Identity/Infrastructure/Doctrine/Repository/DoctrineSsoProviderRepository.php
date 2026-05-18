<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Doctrine\Repository;

use App\Identity\Domain\Entity\SsoProvider;
use App\Identity\Domain\Repository\SsoProviderRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<SsoProvider>
 */
class DoctrineSsoProviderRepository extends ServiceEntityRepository implements SsoProviderRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SsoProvider::class);
    }

    public function findById(Uuid $id): ?SsoProvider
    {
        return parent::find($id->toRfc4122());
    }

    public function findByTenantAndKind(Uuid $tenantId, string $kind): ?SsoProvider
    {
        return $this->findOneBy([
            'tenantId' => $tenantId->toRfc4122(),
            'kind' => $kind,
        ]);
    }

    /**
     * @return list<SsoProvider>
     */
    public function findByTenant(Uuid $tenantId): array
    {
        return $this->findBy(['tenantId' => $tenantId->toRfc4122()]);
    }

    public function save(SsoProvider $entity): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
    }

    public function remove(SsoProvider $entity): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        $em->flush();
    }
}
