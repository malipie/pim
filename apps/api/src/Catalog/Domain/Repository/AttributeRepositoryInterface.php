<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Entity\Attribute;
use App\Shared\Domain\Tenant;
use Symfony\Component\Uid\Uuid;

interface AttributeRepositoryInterface
{
    public function findById(Uuid $id): ?Attribute;

    public function findByCode(string $code, Tenant $tenant): ?Attribute;

    public function save(Attribute $attribute): void;

    public function remove(Attribute $attribute): void;
}
