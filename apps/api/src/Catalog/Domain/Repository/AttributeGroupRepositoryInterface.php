<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Entity\AttributeGroup;
use App\Shared\Domain\Tenant;
use Symfony\Component\Uid\Uuid;

interface AttributeGroupRepositoryInterface
{
    public function findById(Uuid $id): ?AttributeGroup;

    public function findByCode(string $code, Tenant $tenant): ?AttributeGroup;

    public function save(AttributeGroup $attributeGroup): void;

    public function remove(AttributeGroup $attributeGroup): void;
}
