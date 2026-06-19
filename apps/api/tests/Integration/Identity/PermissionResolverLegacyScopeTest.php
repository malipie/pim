<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity;

use App\Identity\Application\PermissionResolverInterface;
use App\Identity\Domain\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * AUD-029 / W3-5.1 (#1611) — the legacy `user_roles` M2M must not neutralise
 * the per-locale/channel scope carried by `user_role_assignments`.
 *
 * Brownfield reality: {@see \App\Identity\Application\UserCreateService} writes
 * every (user, role) pair to BOTH tables — `user_roles` (Symfony Security graph)
 * and `user_role_assignments` (scope columns). The legacy row projects a literal
 * `'[]'` scope which {@see \App\Identity\Application\PermissionResolver::mergeScope}
 * reads as "no restriction" and short-circuits the union to `[]`, silently
 * widening a locale-scoped role to all locales.
 *
 * RED  (pre-fix): a user with role R via `user_role_assignments` (locale=['pl'])
 *      AND a legacy `user_roles` row for the SAME R resolves to localeScope=[]
 *      (most-permissive) — the `'[]'` from the legacy branch wins the union.
 * GREEN (post-fix): the legacy branch is excluded when an assignment covers the
 *      same (user, role); localeScope stays ['pl'].
 *
 * Regression guard: a legacy-ONLY role (no matching assignment — the fixture
 * super_admin / tenant_owner attached via addRole only) keeps localeScope=[]
 * so genuinely unrestricted roles retain full access.
 *
 * Uses the real container Connection + PermissionResolver against the test DB
 * (schema built from ORM metadata), seeds raw rows, and cleans up in a finally.
 */
final class PermissionResolverLegacyScopeTest extends KernelTestCase
{
    #[Test]
    public function legacyUserRolesDoesNotWidenScopedAssignment(): void
    {
        self::bootKernel();
        $connection = $this->connection();

        $suffix = bin2hex(random_bytes(4));
        $tenantId = Uuid::v7()->toRfc4122();
        $roleId = Uuid::v7()->toRfc4122();
        $permissionId = Uuid::v7()->toRfc4122();
        $userId = Uuid::v7()->toRfc4122();
        $assignmentId = Uuid::v7()->toRfc4122();
        $code = 'products.view.scoped.'.$suffix;

        try {
            $this->seedTenant($connection, $tenantId, 'aud029-a-'.$suffix);
            $this->seedRole($connection, $roleId, $tenantId, 'scoped-role-'.$suffix);
            $this->seedPermission($connection, $permissionId, $code);
            $this->linkRolePermission($connection, $roleId, $permissionId);
            $this->seedUser($connection, $userId, $tenantId, 'aud029-'.$suffix.'@demo.localhost');

            // Scoped assignment: role restricted to the `pl` locale.
            $this->seedAssignment($connection, $assignmentId, $userId, $roleId, '["pl"]');
            // Legacy duplicate of the SAME role (UserCreateService writes both).
            $this->seedLegacyUserRole($connection, $userId, $roleId);

            $resolved = $this->resolver()->resolve($this->loadUser($userId));

            self::assertContains($code, $resolved->getCodes());
            // The crux: the legacy `'[]'` row must NOT have widened the scope.
            self::assertSame(
                ['pl'],
                $resolved->getLocaleScope(),
                'A scoped (locale=pl) assignment must stay restricted; the legacy '
                .'user_roles `[]` row must not widen it to most-permissive (all locales).',
            );
            self::assertTrue(
                $resolved->appliesToLocale('pl'),
                'The pl locale is in scope.',
            );
            self::assertFalse(
                $resolved->appliesToLocale('en'),
                'A non-pl locale must be out of scope; a true here means the legacy '
                .'row re-opened the full locale set.',
            );
        } finally {
            $this->cleanup($connection, $userId, $roleId, $permissionId, $tenantId);
        }
    }

    #[Test]
    public function legacyOnlyRoleKeepsMostPermissiveScope(): void
    {
        self::bootKernel();
        $connection = $this->connection();

        $suffix = bin2hex(random_bytes(4));
        $tenantId = Uuid::v7()->toRfc4122();
        $roleId = Uuid::v7()->toRfc4122();
        $permissionId = Uuid::v7()->toRfc4122();
        $userId = Uuid::v7()->toRfc4122();
        $code = 'platform.tenants.manage.legacy.'.$suffix;

        try {
            $this->seedTenant($connection, $tenantId, 'aud029-b-'.$suffix);
            $this->seedRole($connection, $roleId, $tenantId, 'legacy-only-role-'.$suffix);
            $this->seedPermission($connection, $permissionId, $code);
            $this->linkRolePermission($connection, $roleId, $permissionId);
            $this->seedUser($connection, $userId, $tenantId, 'aud029b-'.$suffix.'@demo.localhost');

            // No user_role_assignments row — this models the fixture super_admin /
            // tenant_owner attached via addRole() only.
            $this->seedLegacyUserRole($connection, $userId, $roleId);

            $resolved = $this->resolver()->resolve($this->loadUser($userId));

            self::assertContains($code, $resolved->getCodes());
            self::assertSame(
                [],
                $resolved->getLocaleScope(),
                'A legacy-only role (no scoped assignment) must keep the most-permissive '
                .'empty scope so super_admin / tenant_owner retain full access.',
            );
            self::assertTrue($resolved->appliesToLocale('en'), 'An unrestricted role applies to any locale.');
            self::assertTrue($resolved->appliesToChannel('shopify'), 'An unrestricted role applies to any channel.');
        } finally {
            $this->cleanup($connection, $userId, $roleId, $permissionId, $tenantId);
        }
    }

    private function seedTenant(Connection $connection, string $id, string $code): void
    {
        $connection->executeStatement(
            'INSERT INTO tenants (id, code, name, created_at) VALUES (:id, :code, :name, NOW())',
            ['id' => $id, 'code' => $code, 'name' => $code],
        );
    }

    private function seedRole(Connection $connection, string $id, string $tenantId, string $code): void
    {
        $connection->executeStatement(
            'INSERT INTO roles (id, tenant_id, code, name, created_at) VALUES (:id, :tenant, :code, :name, NOW())',
            ['id' => $id, 'tenant' => $tenantId, 'code' => $code, 'name' => $code],
        );
    }

    private function seedPermission(Connection $connection, string $id, string $code): void
    {
        // resource/action have their own UNIQUE constraint; derive unique values
        // from the (already unique) code so parallel runs never collide.
        $connection->executeStatement(
            'INSERT INTO permissions (id, code, resource, action, created_at) VALUES (:id, :code, :resource, :action, NOW())',
            ['id' => $id, 'code' => $code, 'resource' => $code, 'action' => 'view'],
        );
    }

    private function linkRolePermission(Connection $connection, string $roleId, string $permissionId): void
    {
        $connection->executeStatement(
            'INSERT INTO role_permissions (role_id, permission_id) VALUES (:role, :permission)',
            ['role' => $roleId, 'permission' => $permissionId],
        );
    }

    private function seedUser(Connection $connection, string $id, string $tenantId, string $email): void
    {
        $connection->executeStatement(
            <<<'SQL'
                INSERT INTO users (id, tenant_id, email, password, roles, status, totp_backup_codes, created_at, password_change_required)
                VALUES (:id, :tenant, :email, '', '["ROLE_USER"]', 'active', '[]', NOW(), false)
                SQL,
            ['id' => $id, 'tenant' => $tenantId, 'email' => $email],
        );
    }

    private function seedAssignment(Connection $connection, string $id, string $userId, string $roleId, string $localeScope): void
    {
        $connection->executeStatement(
            <<<'SQL'
                INSERT INTO user_role_assignments (id, user_id, role_id, locale_scope, channel_scope, attribute_group_scope, assigned_at)
                VALUES (:id, :user, :role, CAST(:locale AS json), CAST('[]' AS json), CAST('[]' AS json), NOW())
                SQL,
            ['id' => $id, 'user' => $userId, 'role' => $roleId, 'locale' => $localeScope],
        );
    }

    private function seedLegacyUserRole(Connection $connection, string $userId, string $roleId): void
    {
        $connection->executeStatement(
            'INSERT INTO user_roles (user_id, role_id) VALUES (:user, :role)',
            ['user' => $userId, 'role' => $roleId],
        );
    }

    private function cleanup(
        Connection $connection,
        string $userId,
        string $roleId,
        string $permissionId,
        string $tenantId,
    ): void {
        // FK order: junctions first, then owned rows, then the tenant.
        $connection->executeStatement('DELETE FROM user_role_assignments WHERE user_id = :id', ['id' => $userId]);
        $connection->executeStatement('DELETE FROM user_roles WHERE user_id = :id', ['id' => $userId]);
        $connection->executeStatement('DELETE FROM role_permissions WHERE role_id = :id', ['id' => $roleId]);
        $connection->executeStatement('DELETE FROM users WHERE id = :id', ['id' => $userId]);
        $connection->executeStatement('DELETE FROM permissions WHERE id = :id', ['id' => $permissionId]);
        $connection->executeStatement('DELETE FROM roles WHERE id = :id', ['id' => $roleId]);
        $connection->executeStatement('DELETE FROM tenants WHERE id = :id', ['id' => $tenantId]);
    }

    private function loadUser(string $userId): User
    {
        $user = $this->em()->getRepository(User::class)->find(Uuid::fromString($userId));
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function resolver(): PermissionResolverInterface
    {
        $resolver = self::getContainer()->get(PermissionResolverInterface::class);
        self::assertInstanceOf(PermissionResolverInterface::class, $resolver);

        return $resolver;
    }

    private function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        self::assertInstanceOf(EntityManagerInterface::class, $em);

        return $em;
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');
        self::assertInstanceOf(Connection::class, $connection);

        return $connection;
    }
}
