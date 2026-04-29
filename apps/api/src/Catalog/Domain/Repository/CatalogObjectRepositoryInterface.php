<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\ObjectKind;
use App\Shared\Domain\Tenant;
use Symfony\Component\Uid\Uuid;

interface CatalogObjectRepositoryInterface
{
    public function findById(Uuid $id): ?CatalogObject;

    public function findByCode(string $code, ObjectKind $kind, Tenant $tenant): ?CatalogObject;

    /**
     * @return list<CatalogObject>
     */
    public function findByKind(ObjectKind $kind, Tenant $tenant): array;

    public function save(CatalogObject $object): void;

    public function remove(CatalogObject $object): void;
}
