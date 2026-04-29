<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\Repository;

use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Shared\Domain\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ObjectType>
 */
class ObjectTypeRepository extends ServiceEntityRepository
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
}
