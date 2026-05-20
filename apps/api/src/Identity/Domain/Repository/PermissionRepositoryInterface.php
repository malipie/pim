<?php

declare(strict_types=1);

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Entity\Permission;
use Symfony\Component\Uid\Uuid;

interface PermissionRepositoryInterface
{
    public function findById(Uuid $id): ?Permission;

    public function findByResourceAction(string $resource, string $action): ?Permission;

    public function findByCode(string $code): ?Permission;

    public function save(Permission $entity): void;

    public function remove(Permission $entity): void;

    /**
     * Returns every permission row in the global pool. Used by the dev
     * fixtures + Settings → Roles permission matrix (Phase 6 retrofit)
     * to enumerate the full code surface for grant assignment.
     *
     * @return list<Permission>
     */
    public function findAllOrdered(): array;
}
