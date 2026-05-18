<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * RBAC-P1-008 (#647) — bootstrap 5 missing Identity bundle entities.
 *
 * Creates the tables backing the new Domain\Entity classes added in this
 * commit: SuperAdmin, UserRole (table user_role_assignments), ApiToken,
 * Invitation, UserTenantMembership. Foreign-key constraints are deliberately
 * omitted — they ship with P1-004 (#643) once the full RBAC schema lands
 * with its 10-table referential graph; this migration only unblocks the
 * E2E fixture loader by giving the entities concrete tables to map onto.
 *
 * No data migration — these are brand-new tables; no rows to backfill.
 */
final class Version20260518131500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'RBAC-P1-008 — create super_admins, user_role_assignments, api_tokens, invitations, user_tenant_memberships';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE super_admins (
                id UUID NOT NULL,
                email VARCHAR(255) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'active',
                last_login_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX super_admins_email_uniq ON super_admins (email)');

        $this->addSql(<<<'SQL'
            CREATE TABLE user_role_assignments (
                id UUID NOT NULL,
                user_id UUID NOT NULL,
                role_id UUID NOT NULL,
                locale_scope JSON NOT NULL DEFAULT '[]',
                channel_scope JSON NOT NULL DEFAULT '[]',
                attribute_group_scope JSON NOT NULL DEFAULT '[]',
                assigned_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX user_role_assignments_user_idx ON user_role_assignments (user_id)');
        $this->addSql('CREATE INDEX user_role_assignments_role_idx ON user_role_assignments (role_id)');
        $this->addSql('CREATE UNIQUE INDEX user_role_assignments_user_role_uniq ON user_role_assignments (user_id, role_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE api_tokens (
                id UUID NOT NULL,
                tenant_id UUID NOT NULL,
                user_id UUID NOT NULL,
                name VARCHAR(255) NOT NULL,
                token_hash VARCHAR(255) NOT NULL,
                token_last4 VARCHAR(8) NOT NULL,
                scopes JSON NOT NULL DEFAULT '[]',
                expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                last_used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                last_used_ip VARCHAR(45) DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX api_tokens_tenant_idx ON api_tokens (tenant_id)');
        $this->addSql('CREATE INDEX api_tokens_user_idx ON api_tokens (user_id)');
        $this->addSql('CREATE UNIQUE INDEX api_tokens_token_hash_uniq ON api_tokens (token_hash)');

        $this->addSql(<<<'SQL'
            CREATE TABLE invitations (
                id UUID NOT NULL,
                tenant_id UUID NOT NULL,
                email VARCHAR(255) NOT NULL,
                token_hash VARCHAR(255) NOT NULL,
                invited_by_user_id UUID NOT NULL,
                role_id UUID NOT NULL,
                expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                accepted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX invitations_tenant_idx ON invitations (tenant_id)');
        $this->addSql('CREATE INDEX invitations_email_idx ON invitations (email)');
        $this->addSql('CREATE UNIQUE INDEX invitations_token_hash_uniq ON invitations (token_hash)');

        $this->addSql(<<<'SQL'
            CREATE TABLE user_tenant_memberships (
                id UUID NOT NULL,
                user_id UUID NOT NULL,
                tenant_id UUID NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'pending',
                invited_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                joined_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX user_tenant_memberships_user_idx ON user_tenant_memberships (user_id)');
        $this->addSql('CREATE INDEX user_tenant_memberships_tenant_idx ON user_tenant_memberships (tenant_id)');
        $this->addSql('CREATE UNIQUE INDEX user_tenant_memberships_user_tenant_uniq ON user_tenant_memberships (user_id, tenant_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_tenant_memberships');
        $this->addSql('DROP TABLE invitations');
        $this->addSql('DROP TABLE api_tokens');
        $this->addSql('DROP TABLE user_role_assignments');
        $this->addSql('DROP TABLE super_admins');
    }
}
