<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Shared\Domain\Tenant;
use Symfony\Component\Uid\Uuid;

interface ObjectTypeRepositoryInterface
{
    public function findById(Uuid $id): ?ObjectType;

    public function findByCode(string $code, Tenant $tenant): ?ObjectType;

    /**
     * @return list<ObjectType>
     */
    public function findByKind(ObjectKind $kind, Tenant $tenant): array;

    public function findBuiltInByKind(ObjectKind $kind, Tenant $tenant): ?ObjectType;

    public function save(ObjectType $objectType): void;

    public function remove(ObjectType $objectType): void;
}
