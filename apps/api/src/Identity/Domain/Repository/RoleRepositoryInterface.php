<?php

declare(strict_types=1);

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Entity\Role;
use App\Shared\Domain\Tenant;
use Symfony\Component\Uid\Uuid;

interface RoleRepositoryInterface
{
    public function findById(Uuid $id): ?Role;

    public function findGlobalByCode(string $code): ?Role;

    public function findByCode(string $code, ?Tenant $tenant = null): ?Role;

    public function save(Role $entity): void;

    public function remove(Role $entity): void;

    /**
     * RBAC-P5-005 (#695) — listing for the Settings → Roles page.
     *
     * Returns every global (system) role plus the custom roles defined
     * by the supplied tenant, each paired with the number of users
     * within that tenant who currently hold the role.
     *
     * The shape stays a list of plain arrays rather than a dedicated
     * projection class so the Doctrine repository can use a single
     * QueryBuilder + groupBy without introducing a new domain DTO. The
     * application layer wraps these tuples into JSON via
     * {@see \App\Identity\Application\RoleListResponseBuilder}.
     *
     * @return list<array{role: Role, user_count: int}>
     */
    public function findAllForTenantWithUserCount(Tenant $tenant): array;
}
