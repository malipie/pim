<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * ULV-04a (#985) — seed the five generic universal-ObjectListView
 * permission codes (`object.{view,add,edit,delete,export}`) into the
 * permissions catalogue and grant them to the existing tenant-side
 * roles that should inherit the universal verbs:
 *
 *   - tenant_owner
 *   - admin
 *   - catalog_manager
 *
 * Idempotent: skips inserts when the code already exists, skips grants
 * when role_permissions already carries the row. Re-running after a
 * fresh `PrdPermissionFixtures` is a no-op.
 *
 * Per-role grants for `marketing`/`modeler`/`integration_manager`/
 * `channel_manager` are intentionally NOT auto-granted — these roles
 * already have narrower per-kind grants (`products.view` etc.) and
 * `object.*` is a strict superset; granting it here would silently
 * widen their scope.
 */
final class Version20260525200000 extends AbstractMigration
{
    private const array CODES = [
        'object.view',
        'object.add',
        'object.edit',
        'object.delete',
        'object.export',
    ];

    private const array INHERITING_ROLES = [
        'tenant_owner',
        'admin',
        'catalog_manager',
    ];

    public function getDescription(): string
    {
        return 'ULV-04a (#985): seed object.{view,add,edit,delete,export} PRD codes + grant to tenant_owner/admin/catalog_manager.';
    }

    public function up(Schema $schema): void
    {
        foreach (self::CODES as $code) {
            $parts = explode('.', $code);
            $resource = $parts[0];
            $action = $parts[1];

            $this->addSql(<<<'SQL'
                INSERT INTO permissions (id, code, resource, action, created_at)
                SELECT gen_random_uuid(), :code, :resource, :action, NOW()
                WHERE NOT EXISTS (SELECT 1 FROM permissions WHERE code = :code)
            SQL, ['code' => $code, 'resource' => $resource, 'action' => $action]);
        }

        foreach (self::INHERITING_ROLES as $roleCode) {
            foreach (self::CODES as $code) {
                $this->addSql(<<<'SQL'
                    INSERT INTO role_permissions (role_id, permission_id)
                    SELECT r.id, p.id
                    FROM roles r, permissions p
                    WHERE r.code = :role_code AND p.code = :perm_code
                      AND NOT EXISTS (
                          SELECT 1 FROM role_permissions rp
                          WHERE rp.role_id = r.id AND rp.permission_id = p.id
                      )
                SQL, ['role_code' => $roleCode, 'perm_code' => $code]);
            }
        }
    }

    public function down(Schema $schema): void
    {
        foreach (self::CODES as $code) {
            $this->addSql(<<<'SQL'
                DELETE FROM role_permissions
                WHERE permission_id IN (SELECT id FROM permissions WHERE code = :code)
            SQL, ['code' => $code]);

            $this->addSql(<<<'SQL'
                DELETE FROM permissions WHERE code = :code
            SQL, ['code' => $code]);
        }
    }
}
