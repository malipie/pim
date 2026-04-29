<?php

declare(strict_types=1);

namespace App\Identity\Domain\Repository;

interface PermissionRepositoryInterface
{
    public function findById(\Symfony\Component\Uid\Uuid $id): ?\App\Identity\Domain\Entity\Permission;

    public function findByResourceAction(string $resource, string $action): ?\App\Identity\Domain\Entity\Permission;

    public function findByCode(string $code): ?\App\Identity\Domain\Entity\Permission;

    public function save(\App\Identity\Domain\Entity\Permission $entity): void;

    public function remove(\App\Identity\Domain\Entity\Permission $entity): void;
}
