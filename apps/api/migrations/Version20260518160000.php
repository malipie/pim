<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * RBAC-P1-005 (#644) — delta migrations enabling the 3-state attribute
 * permission model (PRD-PIM-rbac §3.5) + audit_logs creation (§4.3).
 *
 * Net delta:
 *   1. attributes.integration_visible BOOLEAN DEFAULT true — Marketing /
 *      Channel Manager can render an attribute publicly only when this
 *      flag is true; credentials and supplier-side internals stay hidden.
 *   2. roles.default_attribute_permission VARCHAR(16) DEFAULT 'edit' —
 *      per-role baseline (PRD §3.5 resolution chain: attribute override →
 *      group override → role default).
 *   3. CREATE TABLE role_attribute_permissions — per-attribute 3-state
 *      grant (restricted/view/edit), keyed by (role_id, attribute_id).
 *   4. CREATE TABLE role_attribute_group_permissions — per-group 3-state
 *      grant, keyed by (role_id, attribute_group_id).
 *   5. CREATE TABLE audit_logs — RBAC-aware audit log per PRD §4.3
 *      (the existing dh-auditor bundle writes per-entity *_audit tables;
 *      this is a separate orthogonal log for permission-check results
 *      and cross-tenant Super Admin actions).
 *
 * Data initialisation of `roles.default_attribute_permission` for the
 * pre-existing seeded built-in roles (super_admin / catalog_manager /
 * integration_manager / viewer) is handled in the same migration as a
 * targeted UPDATE — keeps the rollout atomic.
 */
final class Version20260518160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'RBAC-P1-005 — 3-state attribute permissions + audit_logs (PRD §3.5, §4.3)';
    }

    public function up(Schema $schema): void
    {
        // ─── 1. attributes.integration_visible ──────────────────────────
        $this->addSql(<<<'SQL'
            ALTER TABLE attributes
                ADD COLUMN integration_visible BOOLEAN NOT NULL DEFAULT TRUE
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_attributes_integration_visible
                ON attributes (integration_visible)
                WHERE integration_visible = FALSE
        SQL);
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN attributes.integration_visible
                IS 'False hides the attribute from non-internal channels (Marketing/Channel Manager output, public APIs). PRD-PIM-rbac §3.5.'
        SQL);

        // ─── 2. roles.default_attribute_permission ──────────────────────
        $this->addSql(<<<'SQL'
            ALTER TABLE roles
                ADD COLUMN default_attribute_permission VARCHAR(16) NOT NULL DEFAULT 'edit'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE roles
                ADD CONSTRAINT chk_roles_default_attribute_permission
                CHECK (default_attribute_permission IN ('restricted', 'view', 'edit'))
        SQL);

        // Seed sane defaults for the four built-in roles already shipped
        // by RbacSeeder pre-RBAC marathon. Viewer is read-only; the rest
        // edit by default (per PRD §3.2 macierz).
        $this->addSql(<<<'SQL'
            UPDATE roles SET default_attribute_permission = 'view'
                WHERE code = 'viewer'
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE roles SET default_attribute_permission = 'edit'
                WHERE code IN ('super_admin', 'catalog_manager', 'integration_manager')
        SQL);

        // ─── 3. role_attribute_permissions (per-attribute override) ─────
        $this->addSql(<<<'SQL'
            CREATE TABLE role_attribute_permissions (
                role_id UUID NOT NULL,
                attribute_id UUID NOT NULL,
                permission VARCHAR(16) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (role_id, attribute_id),
                CONSTRAINT chk_role_attribute_permissions_value
                    CHECK (permission IN ('restricted', 'view', 'edit')),
                CONSTRAINT fk_role_attribute_permissions_role
                    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
                CONSTRAINT fk_role_attribute_permissions_attribute
                    FOREIGN KEY (attribute_id) REFERENCES attributes(id) ON DELETE CASCADE
            )
        SQL);
        $this->addSql('CREATE INDEX idx_role_attribute_permissions_role ON role_attribute_permissions (role_id)');
        $this->addSql('CREATE INDEX idx_role_attribute_permissions_attribute ON role_attribute_permissions (attribute_id)');

        // ─── 4. role_attribute_group_permissions (per-group override) ───
        $this->addSql(<<<'SQL'
            CREATE TABLE role_attribute_group_permissions (
                role_id UUID NOT NULL,
                attribute_group_id UUID NOT NULL,
                permission VARCHAR(16) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (role_id, attribute_group_id),
                CONSTRAINT chk_role_attribute_group_permissions_value
                    CHECK (permission IN ('restricted', 'view', 'edit')),
                CONSTRAINT fk_role_attribute_group_permissions_role
                    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
                CONSTRAINT fk_role_attribute_group_permissions_group
                    FOREIGN KEY (attribute_group_id) REFERENCES attribute_groups(id) ON DELETE CASCADE
            )
        SQL);
        $this->addSql('CREATE INDEX idx_role_attribute_group_permissions_role ON role_attribute_group_permissions (role_id)');

        // ─── 5. audit_logs — RBAC-aware audit (PRD §4.3) ────────────────
        // Orthogonal to the dh-auditor bundle's per-entity *_audit tables;
        // this one captures permission-check decisions and cross-tenant
        // Super Admin operations.
        $this->addSql(<<<'SQL'
            CREATE TABLE audit_logs (
                id UUID NOT NULL,
                tenant_id UUID DEFAULT NULL,
                user_id UUID DEFAULT NULL,
                super_admin_id UUID DEFAULT NULL,
                action VARCHAR(64) NOT NULL,
                resource_type VARCHAR(64) NOT NULL,
                resource_id VARCHAR(255) DEFAULT NULL,
                old_value JSON DEFAULT NULL,
                new_value JSON DEFAULT NULL,
                permission_check_result VARCHAR(32) DEFAULT NULL,
                cross_tenant_access BOOLEAN NOT NULL DEFAULT FALSE,
                special_flags JSON NOT NULL DEFAULT '[]',
                ip_address VARCHAR(45) DEFAULT NULL,
                user_agent TEXT DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                CONSTRAINT chk_audit_logs_permission_check_result
                    CHECK (permission_check_result IS NULL OR permission_check_result IN ('granted', 'denied', 'n_a', 'super_admin_bypass'))
            )
        SQL);
        $this->addSql('CREATE INDEX idx_audit_logs_tenant ON audit_logs (tenant_id)');
        $this->addSql('CREATE INDEX idx_audit_logs_user ON audit_logs (user_id)');
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_audit_logs_super_admin
                ON audit_logs (super_admin_id)
                WHERE super_admin_id IS NOT NULL
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_audit_logs_cross_tenant
                ON audit_logs (cross_tenant_access)
                WHERE cross_tenant_access = TRUE
        SQL);
        $this->addSql('CREATE INDEX idx_audit_logs_resource ON audit_logs (resource_type, resource_id)');
        $this->addSql('CREATE INDEX idx_audit_logs_created_at ON audit_logs (created_at)');
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_audit_logs_special_flags
                ON audit_logs USING GIN (special_flags)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE audit_logs ADD CONSTRAINT fk_audit_logs_tenant
                FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE audit_logs ADD CONSTRAINT fk_audit_logs_user
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE audit_logs ADD CONSTRAINT fk_audit_logs_super_admin
                FOREIGN KEY (super_admin_id) REFERENCES super_admins(id) ON DELETE SET NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE audit_logs');
        $this->addSql('DROP TABLE role_attribute_group_permissions');
        $this->addSql('DROP TABLE role_attribute_permissions');
        $this->addSql('ALTER TABLE roles DROP CONSTRAINT chk_roles_default_attribute_permission');
        $this->addSql('ALTER TABLE roles DROP COLUMN default_attribute_permission');
        $this->addSql('DROP INDEX idx_attributes_integration_visible');
        $this->addSql('ALTER TABLE attributes DROP COLUMN integration_visible');
    }
}
