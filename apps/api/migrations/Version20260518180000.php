<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * RBAC-P2-009 (#658) — password_reset_tokens table for the password
 * reset flow. SHA-256 hashed tokens, single-use, 1h TTL.
 *
 * FK strategy: ON DELETE CASCADE to users + tenants — if either is
 * deleted the pending reset attempts go with them (no orphan tokens).
 */
final class Version20260518180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'RBAC-P2-009 — create password_reset_tokens table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE password_reset_tokens (
                id UUID NOT NULL,
                tenant_id UUID NOT NULL,
                user_id UUID NOT NULL,
                token_hash VARCHAR(255) NOT NULL,
                expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT fk_password_reset_tokens_tenant
                    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
                CONSTRAINT fk_password_reset_tokens_user
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX password_reset_tokens_token_hash_uniq ON password_reset_tokens (token_hash)');
        $this->addSql('CREATE INDEX password_reset_tokens_user_idx ON password_reset_tokens (user_id)');
        $this->addSql('CREATE INDEX password_reset_tokens_tenant_idx ON password_reset_tokens (tenant_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE password_reset_tokens');
    }
}
