<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * RBAC-P1-004 (#643) — close the 10-table schema graph from PRD-PIM-rbac §4.3.
 *
 * P1-008 (#647) created 5 tables (super_admins, user_role_assignments,
 * api_tokens, invitations, user_tenant_memberships) without FK constraints
 * — the entities use denormalised UUIDs (RefreshToken pattern) so the
 * Doctrine mapping never joins; referential integrity is enforced at the
 * schema level, which this migration adds.
 *
 * Tables that already exist via earlier migrations:
 *   users, roles, permissions, role_permissions (pre-RBAC Sprint-0 work).
 *
 * Net delta for this migration:
 *   1. CREATE TABLE sso_providers (the 10th PRD table; Phase 2 #661 will
 *      flesh out the SSO authenticator stack, but the schema lands now
 *      so the cross-tenant audit + 10-table inventory is closed today).
 *   2. ALTER TABLE …user_role_assignments / api_tokens / invitations /
 *      user_tenant_memberships ADD CONSTRAINT FK with ON DELETE CASCADE
 *      to keep the referential graph honest.
 *
 * Down: drops the FKs and sso_providers. The five P1-008 tables stay —
 * they ship in Version20260518131500.
 */
final class Version20260518150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'RBAC-P1-004 — create sso_providers + add FK constraints to 4 P1-008 tables (PRD-PIM-rbac §4.3 graph close)';
    }

    public function up(Schema $schema): void
    {
        // ─── sso_providers — 10th PRD table; Phase 2 #661 wires the authenticators ───
        $this->addSql(<<<'SQL'
            CREATE TABLE sso_providers (
                id UUID NOT NULL,
                tenant_id UUID NOT NULL,
                kind VARCHAR(32) NOT NULL,
                name VARCHAR(255) NOT NULL,
                config JSON NOT NULL DEFAULT '{}',
                enabled BOOLEAN NOT NULL DEFAULT FALSE,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX sso_providers_tenant_idx ON sso_providers (tenant_id)');
        $this->addSql('CREATE UNIQUE INDEX sso_providers_tenant_kind_uniq ON sso_providers (tenant_id, kind)');
        $this->addSql(<<<'SQL'
            COMMENT ON COLUMN sso_providers.kind IS 'google_workspace | microsoft_365 | saml — see Phase 2 #661 for authenticator wiring'
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE sso_providers ADD CONSTRAINT fk_sso_providers_tenant
                FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        SQL);

        // ─── FKs on P1-008 tables ───────────────────────────────────────
        $this->addSql(<<<'SQL'
            ALTER TABLE user_role_assignments
                ADD CONSTRAINT fk_user_role_assignments_user
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_role_assignments
                ADD CONSTRAINT fk_user_role_assignments_role
                FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE api_tokens
                ADD CONSTRAINT fk_api_tokens_tenant
                FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE api_tokens
                ADD CONSTRAINT fk_api_tokens_user
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE invitations
                ADD CONSTRAINT fk_invitations_tenant
                FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE invitations
                ADD CONSTRAINT fk_invitations_invited_by
                FOREIGN KEY (invited_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE invitations
                ADD CONSTRAINT fk_invitations_role
                FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE user_tenant_memberships
                ADD CONSTRAINT fk_user_tenant_memberships_user
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_tenant_memberships
                ADD CONSTRAINT fk_user_tenant_memberships_tenant
                FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_tenant_memberships DROP CONSTRAINT fk_user_tenant_memberships_tenant');
        $this->addSql('ALTER TABLE user_tenant_memberships DROP CONSTRAINT fk_user_tenant_memberships_user');
        $this->addSql('ALTER TABLE invitations DROP CONSTRAINT fk_invitations_role');
        $this->addSql('ALTER TABLE invitations DROP CONSTRAINT fk_invitations_invited_by');
        $this->addSql('ALTER TABLE invitations DROP CONSTRAINT fk_invitations_tenant');
        $this->addSql('ALTER TABLE api_tokens DROP CONSTRAINT fk_api_tokens_user');
        $this->addSql('ALTER TABLE api_tokens DROP CONSTRAINT fk_api_tokens_tenant');
        $this->addSql('ALTER TABLE user_role_assignments DROP CONSTRAINT fk_user_role_assignments_role');
        $this->addSql('ALTER TABLE user_role_assignments DROP CONSTRAINT fk_user_role_assignments_user');
        $this->addSql('DROP TABLE sso_providers');
    }
}
