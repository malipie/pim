<?php

declare(strict_types=1);

namespace App\Identity\Domain\Repository;

interface RoleRepositoryInterface
{
    public function findById(\Symfony\Component\Uid\Uuid $id): ?\App\Identity\Domain\Entity\Role;

    public function findGlobalByCode(string $code): ?\App\Identity\Domain\Entity\Role;

    public function findByCode(string $code, ?\App\Shared\Domain\Tenant $tenant = null): ?\App\Identity\Domain\Entity\Role;

    public function save(\App\Identity\Domain\Entity\Role $entity): void;

    public function remove(\App\Identity\Domain\Entity\Role $entity): void;
}
