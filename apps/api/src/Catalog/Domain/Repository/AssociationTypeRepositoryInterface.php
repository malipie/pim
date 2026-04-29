<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Entity\AssociationType;
use App\Shared\Domain\Tenant;
use Symfony\Component\Uid\Uuid;

interface AssociationTypeRepositoryInterface
{
    public function findById(Uuid $id): ?AssociationType;

    public function findByCode(string $code, Tenant $tenant): ?AssociationType;

    public function save(AssociationType $associationType): void;

    public function remove(AssociationType $associationType): void;
}
