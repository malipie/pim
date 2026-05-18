<?php

declare(strict_types=1);

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Entity\UserRole;
use Symfony\Component\Uid\Uuid;

interface UserRoleRepositoryInterface
{
    public function findById(Uuid $id): ?UserRole;

    /**
     * @return list<UserRole>
     */
    public function findByUser(Uuid $userId): array;

    /**
     * @return list<UserRole>
     */
    public function findByRole(Uuid $roleId): array;

    public function findByUserAndRole(Uuid $userId, Uuid $roleId): ?UserRole;

    public function save(UserRole $entity): void;

    public function remove(UserRole $entity): void;
}
