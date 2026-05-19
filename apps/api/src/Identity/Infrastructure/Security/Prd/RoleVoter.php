<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security\Prd;

use App\Identity\Domain\Entity\Role;
use App\Identity\Infrastructure\Security\AbstractPrdVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * RBAC-P3-005 (#668) — per-Role authorization aligned with the
 * PRD §3.2 macierz Settings → Roles single-permission row
 * (`settings.roles.manage`).
 *
 * Every action (view / add / edit / delete) maps to the same Tenant
 * Owner / Administrator permission, matching the UserVoter pattern.
 *
 * Tenant boundary: AbstractPrdVoter's default tenant compare skips when
 * the subject's tenant is null (a global / built-in row). For Role that
 * default is unsafe — null-tenant means "seeded platform-owned template
 * like super_admin / viewer", which tenant-scoped `settings.roles.manage`
 * must not mutate. This voter overrides the comparison to deny outright
 * when `Role::isGlobal()` is true while the caller is tenant-scoped.
 *
 * Built-in / system role protection (e.g. blocking delete of the seeded
 * `tenant_owner` template inside a tenant) is enforced at the service
 * layer where the 409 message belongs — Role entity has no `is_system`
 * column today, so adding a runtime check here would require a schema
 * change that belongs to its own ticket.
 */
final class RoleVoter extends AbstractPrdVoter
{
    /**
     * @return array<string, string>
     */
    protected function permissionMap(): array
    {
        return [
            'view' => 'settings.roles.manage',
            'add' => 'settings.roles.manage',
            'edit' => 'settings.roles.manage',
            'delete' => 'settings.roles.manage',
        ];
    }

    protected function subjectClass(): string
    {
        return Role::class;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        if (!parent::voteOnAttribute($attribute, $subject, $token)) {
            return false;
        }

        // Global / platform-owned roles (null tenant) cannot be mutated by
        // any tenant-scoped principal — the macierz code is tenant-scoped.
        return !($subject instanceof Role && $subject->isGlobal());
    }
}
