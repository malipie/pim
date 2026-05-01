<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\Repository;

use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Shared\Domain\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ObjectType>
 */
class DoctrineObjectTypeRepository extends ServiceEntityRepository implements ObjectTypeRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ObjectType::class);
    }

    public function findByCode(string $code, Tenant $tenant): ?ObjectType
    {
        return $this->findOneBy(['code' => $code, 'tenant' => $tenant]);
    }

    /**
     * @return list<ObjectType>
     */
    public function findByKind(ObjectKind $kind, Tenant $tenant): array
    {
        /** @var list<ObjectType> $rows */
        $rows = $this->findBy(['kind' => $kind, 'tenant' => $tenant]);

        return $rows;
    }

    /**
     * @return list<ObjectType>
     */
    public function findAllByTenant(Tenant $tenant): array
    {
        /** @var list<ObjectType> $rows */
        $rows = $this->findBy(['tenant' => $tenant]);

        return $rows;
    }

    /**
     * Returns the platform-owned built-in ObjectType for a given kind, if
     * any. After #33 (predefined fixtures) every tenant carries one row
     * per built-in kind; this helper is the canonical lookup for "the
     * default product type for this tenant".
     */
    public function findBuiltInByKind(ObjectKind $kind, Tenant $tenant): ?ObjectType
    {
        return $this->findOneBy([
            'kind' => $kind,
            'tenant' => $tenant,
            'isBuiltIn' => true,
        ]);
    }

    public function findById(\Symfony\Component\Uid\Uuid $id): ?ObjectType
    {
        return parent::find($id->toRfc4122());
    }

    public function save(ObjectType $entity): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
    }

    public function remove(ObjectType $entity): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        $em->flush();
    }
}
