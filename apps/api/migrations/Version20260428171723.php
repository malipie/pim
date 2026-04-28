<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Epic 0.2 / ticket #28 — refresh tokens with rotation + family theft detection.
 *
 * `refresh_tokens` is internal storage for the rotating refresh token issued
 * at login and consumed on POST /api/auth/refresh. The raw token only ever
 * exists in the httpOnly cookie; what we persist is `token_hash` (SHA-256 hex,
 * 64 chars) — unique so a stolen DB cannot be replayed against the auth
 * endpoint without the original cookie.
 *
 * `family_id` ties every token derived from the same login together so reuse
 * of an already-used (`used_at IS NOT NULL`) token can revoke the whole
 * lineage in one UPDATE — see RefreshTokenService::rotate.
 *
 * FK strategy:
 *   - tenant_id → tenants(id) ON DELETE CASCADE — tokens follow the tenant
 *   - user_id   → users(id)   ON DELETE CASCADE — tokens follow the user
 */
final class Version20260428171723 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add refresh_tokens table for /api/auth refresh rotation + theft detection (#28).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE refresh_tokens (
              id UUID NOT NULL,
              tenant_id UUID NOT NULL,
              user_id UUID NOT NULL,
              family_id UUID NOT NULL,
              token_hash VARCHAR(64) NOT NULL,
              issued_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX refresh_tokens_tenant_idx ON refresh_tokens (tenant_id)');
        $this->addSql('CREATE INDEX refresh_tokens_user_idx ON refresh_tokens (user_id)');
        $this->addSql('CREATE INDEX refresh_tokens_family_idx ON refresh_tokens (family_id)');
        $this->addSql('CREATE UNIQUE INDEX refresh_tokens_token_hash_uniq ON refresh_tokens (token_hash)');
        $this->addSql('ALTER TABLE refresh_tokens ADD CONSTRAINT refresh_tokens_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE refresh_tokens ADD CONSTRAINT refresh_tokens_user_fk FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE refresh_tokens DROP CONSTRAINT refresh_tokens_user_fk');
        $this->addSql('ALTER TABLE refresh_tokens DROP CONSTRAINT refresh_tokens_tenant_fk');
        $this->addSql('DROP TABLE refresh_tokens');
    }
}
