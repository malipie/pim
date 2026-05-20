<?php

declare(strict_types=1);

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Entity\User;
use App\Shared\Domain\Tenant;
use Symfony\Component\Uid\Uuid;

interface UserRepositoryInterface
{
    public function findById(Uuid $id): ?User;

    public function findByEmail(string $email): ?User;

    public function save(User $entity): void;

    public function remove(User $entity): void;

    /**
     * Paginated listing within a tenant scope, used by the Settings → Users
     * page (RBAC-P5-001 #691). The Doctrine TenantFilter already narrows
     * the result set to the active tenant on every read; the explicit
     * `$tenant` argument here is what the controller logs / audits so the
     * filter cannot be silently disabled by a misconfigured listener.
     *
     * Filters:
     *  - `$status` — null (any), 'active' or 'disabled' (PRD STATUS_* values);
     *  - `$roleIds` — null (any), or a list of Role UUIDs (M2M intersection);
     *  - `$search` — null or a substring matched against `email` (case-insensitive
     *    via lowercased comparison). Email is the only display identifier today
     *    (no separate first_name / last_name in `users` table yet).
     *
     * Pagination is 1-indexed; `$perPage` is clamped by the controller to the
     * Settings UI maximum of 50 (PRD §5.3 mockup spec).
     *
     * @param list<string>|null $roleIds Role UUIDs as RFC 4122 strings
     *
     * @return list<User>
     */
    public function findAllByTenantPaginated(
        Tenant $tenant,
        ?string $status,
        ?array $roleIds,
        ?string $search,
        int $page,
        int $perPage,
    ): array;

    /**
     * Same filter contract as {@see findAllByTenantPaginated()}, returns the
     * total row count for pager metadata. Two queries instead of a single
     * `OVER()` window so we keep the JOIN-on-roles simple — at the
     * Settings-UI scale (<1k users / tenant) the second query is cheap.
     *
     * @param list<string>|null $roleIds
     */
    public function countByTenant(
        Tenant $tenant,
        ?string $status,
        ?array $roleIds,
        ?string $search,
    ): int;

    /**
     * RBAC-P5-006 (#696) — number of users on any tenant who currently
     * hold the given role via the M2M assignment table. Used by the
     * custom-role-builder DELETE path to refuse a delete that would
     * leave dangling references (operator must reassign or strip the
     * role first).
     */
    public function countAssignedToRole(Uuid $roleId): int;
}
