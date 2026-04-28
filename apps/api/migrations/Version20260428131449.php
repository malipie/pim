<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Epic 0.2 / ticket #24 — RBAC schema baseline.
 *
 * Adds the Identity & Access tables required by the rest of the epic:
 * `roles` and `permissions` plus the M2M junctions `user_roles` and
 * `role_permissions`. Extends the existing `users` table with `status` and
 * `last_login_at`, and `tenants` with `domain` (unique) and `plan`.
 *
 * Roles with NULL tenant_id are global (built-in seeder roles in #27 —
 * super_admin / catalog_manager / integration_manager / viewer). FK strategy:
 *   - role_permissions / user_roles → ON DELETE CASCADE (junctions follow
 *     their parents, so removing a role or permission cleans up its links)
 *   - roles.tenant_id → ON DELETE CASCADE (custom per-tenant roles disappear
 *     with their tenant; global roles have NULL and are unaffected)
 *   - users.tenant_id remains RESTRICT from #4 (cannot delete a tenant with
 *     active users by accident).
 */
final class Version20260428131449 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add RBAC tables (roles, permissions, user_roles, role_permissions) and extend users/tenants for #24.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE permissions (
              id UUID NOT NULL,
              code VARCHAR(128) NOT NULL,
              resource VARCHAR(64) NOT NULL,
              action VARCHAR(32) NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX permissions_resource_action_uniq ON permissions (resource, action)');
        $this->addSql('CREATE UNIQUE INDEX permissions_code_uniq ON permissions (code)');

        $this->addSql(<<<'SQL'
            CREATE TABLE roles (
              id UUID NOT NULL,
              tenant_id UUID DEFAULT NULL,
              code VARCHAR(64) NOT NULL,
              name VARCHAR(255) NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX roles_tenant_idx ON roles (tenant_id)');
        $this->addSql('CREATE UNIQUE INDEX roles_tenant_code_uniq ON roles (tenant_id, code)');
        $this->addSql('ALTER TABLE roles ADD CONSTRAINT roles_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE');

        $this->addSql(<<<'SQL'
            CREATE TABLE role_permissions (
              role_id UUID NOT NULL,
              permission_id UUID NOT NULL,
              PRIMARY KEY (role_id, permission_id)
            )
        SQL);
        $this->addSql('CREATE INDEX role_permissions_role_idx ON role_permissions (role_id)');
        $this->addSql('CREATE INDEX role_permissions_permission_idx ON role_permissions (permission_id)');
        $this->addSql('ALTER TABLE role_permissions ADD CONSTRAINT role_permissions_role_fk FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE role_permissions ADD CONSTRAINT role_permissions_permission_fk FOREIGN KEY (permission_id) REFERENCES permissions (id) ON DELETE CASCADE NOT DEFERRABLE');

        $this->addSql(<<<'SQL'
            CREATE TABLE user_roles (
              user_id UUID NOT NULL,
              role_id UUID NOT NULL,
              PRIMARY KEY (user_id, role_id)
            )
        SQL);
        $this->addSql('CREATE INDEX user_roles_user_idx ON user_roles (user_id)');
        $this->addSql('CREATE INDEX user_roles_role_idx ON user_roles (role_id)');
        $this->addSql('ALTER TABLE user_roles ADD CONSTRAINT user_roles_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE user_roles ADD CONSTRAINT user_roles_role_fk FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE NOT DEFERRABLE');

        $this->addSql('ALTER TABLE tenants ADD domain VARCHAR(255) DEFAULT NULL');
        $this->addSql("ALTER TABLE tenants ADD plan VARCHAR(32) DEFAULT 'starter' NOT NULL");
        $this->addSql('CREATE UNIQUE INDEX tenants_domain_uniq ON tenants (domain)');

        $this->addSql("ALTER TABLE users ADD status VARCHAR(16) DEFAULT 'active' NOT NULL");
        $this->addSql('ALTER TABLE users ADD last_login_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP last_login_at');
        $this->addSql('ALTER TABLE users DROP status');
        $this->addSql('DROP INDEX tenants_domain_uniq');
        $this->addSql('ALTER TABLE tenants DROP plan');
        $this->addSql('ALTER TABLE tenants DROP domain');

        $this->addSql('ALTER TABLE user_roles DROP CONSTRAINT user_roles_role_fk');
        $this->addSql('ALTER TABLE user_roles DROP CONSTRAINT user_roles_user_fk');
        $this->addSql('DROP TABLE user_roles');

        $this->addSql('ALTER TABLE role_permissions DROP CONSTRAINT role_permissions_permission_fk');
        $this->addSql('ALTER TABLE role_permissions DROP CONSTRAINT role_permissions_role_fk');
        $this->addSql('DROP TABLE role_permissions');

        $this->addSql('ALTER TABLE roles DROP CONSTRAINT roles_tenant_fk');
        $this->addSql('DROP TABLE roles');

        $this->addSql('DROP TABLE permissions');
    }
}
