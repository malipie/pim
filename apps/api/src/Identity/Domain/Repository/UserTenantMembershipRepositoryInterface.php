<?php

declare(strict_types=1);

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Entity\UserTenantMembership;
use Symfony\Component\Uid\Uuid;

interface UserTenantMembershipRepositoryInterface
{
    public function findById(Uuid $id): ?UserTenantMembership;

    /**
     * @return list<UserTenantMembership>
     */
    public function findByUser(Uuid $userId): array;

    /**
     * @return list<UserTenantMembership>
     */
    public function findByTenant(Uuid $tenantId): array;

    public function findByUserAndTenant(Uuid $userId, Uuid $tenantId): ?UserTenantMembership;

    public function save(UserTenantMembership $entity): void;

    public function remove(UserTenantMembership $entity): void;
}
