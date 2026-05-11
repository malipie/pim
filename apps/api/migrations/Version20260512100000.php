<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * VIEW-IMP-03 (#500) — `import_sources` + `import_source_logs`.
 *
 * `import_sources` holds the connection configuration the operator
 * registers from the UI. `auth_ref` is a pointer to the Symfony Secrets
 * Vault entry (never the credential plaintext). `import_source_logs`
 * carries the audit trail of health-checks + file pickups (the polling
 * daemon ships in the follow-up, so the table starts mostly empty —
 * health-check probes populate it from day one).
 */
final class Version20260512100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'VIEW-IMP-03: import_sources + import_source_logs.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE import_sources (
    id UUID NOT NULL,
    tenant_id UUID NOT NULL,
    user_id UUID NOT NULL,
    profile_id UUID DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(64) NOT NULL,
    type VARCHAR(16) NOT NULL,
    host TEXT DEFAULT NULL,
    path TEXT DEFAULT NULL,
    file_pattern VARCHAR(255) DEFAULT NULL,
    auth_ref VARCHAR(128) DEFAULT NULL,
    poll_interval_sec INTEGER DEFAULT NULL,
    autotrigger BOOLEAN NOT NULL DEFAULT FALSE,
    health VARCHAR(8) NOT NULL DEFAULT 'off',
    health_checked_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
    health_note TEXT DEFAULT NULL,
    last_pickup_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
    files24h INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE UNIQUE INDEX import_sources_tenant_code_uniq ON import_sources (tenant_id, code)');
        $this->addSql('CREATE INDEX import_sources_tenant_user_idx ON import_sources (tenant_id, user_id)');
        $this->addSql(<<<'SQL'
ALTER TABLE import_sources
    ADD CONSTRAINT FK_import_sources_tenant FOREIGN KEY (tenant_id)
        REFERENCES tenants(id) ON DELETE RESTRICT
SQL);
        $this->addSql(<<<'SQL'
ALTER TABLE import_sources
    ADD CONSTRAINT FK_import_sources_profile FOREIGN KEY (profile_id)
        REFERENCES import_profiles(id) ON DELETE SET NULL
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE import_source_logs (
    id UUID NOT NULL,
    source_id UUID NOT NULL,
    tenant_id UUID NOT NULL,
    event_type VARCHAR(32) NOT NULL,
    severity VARCHAR(8) NOT NULL,
    payload JSONB DEFAULT '{}'::jsonb NOT NULL,
    created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE INDEX import_source_logs_source_idx ON import_source_logs (source_id, created_at DESC)');
        $this->addSql(<<<'SQL'
ALTER TABLE import_source_logs
    ADD CONSTRAINT FK_import_source_logs_source FOREIGN KEY (source_id)
        REFERENCES import_sources(id) ON DELETE CASCADE
SQL);
        $this->addSql(<<<'SQL'
ALTER TABLE import_source_logs
    ADD CONSTRAINT FK_import_source_logs_tenant FOREIGN KEY (tenant_id)
        REFERENCES tenants(id) ON DELETE RESTRICT
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS import_source_logs');
        $this->addSql('DROP TABLE IF EXISTS import_sources');
    }
}
