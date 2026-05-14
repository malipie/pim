<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * EXP-01 (#580) — Exports MVP schema.
 *
 * Three tables backing the Eksport produktów feature (PRD §5.1):
 *   - `export_sessions`: per-run audit row (history + status + filter
 *     snapshot for rerun).
 *   - `export_profiles`: per-user saved configuration (column picker,
 *     locale/channel toggles, format) — unique per (tenant, user, name).
 *   - `export_logs`: per-job log lines (info / warning / error) cascading
 *     on session delete.
 *
 * Indexes mirror PRD §5.1: tenant + user + started_at for list, status
 * partial index for live queue, session FK for logs.
 *
 * Tenant isolation rides on `tenant_id NOT NULL` from day 1 (CLAUDE.md
 * §11.1). RLS is phase-1; the Doctrine TenantFilter is the MVP barrier.
 */
final class Version20260515000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'EXP-01 exports schema: export_sessions, export_profiles, export_logs (#580).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE export_profiles (
                id UUID NOT NULL,
                tenant_id UUID NOT NULL,
                user_id UUID NOT NULL,
                name VARCHAR(255) NOT NULL,
                description TEXT DEFAULT NULL,
                config JSONB NOT NULL DEFAULT '{}'::jsonb,
                last_run_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                run_count INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
            SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX export_profiles_tenant_user_name_uniq
                ON export_profiles (tenant_id, user_id, name)
            SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX export_profiles_tenant_user_idx
                ON export_profiles (tenant_id, user_id)
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE export_profiles
                ADD CONSTRAINT fk_export_profiles_tenant
                FOREIGN KEY (tenant_id) REFERENCES tenants(id)
                ON DELETE RESTRICT
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE export_sessions (
                id UUID NOT NULL,
                tenant_id UUID NOT NULL,
                user_id UUID NOT NULL,
                profile_id UUID DEFAULT NULL,
                source VARCHAR(32) NOT NULL,
                format VARCHAR(8) NOT NULL,
                encoding VARCHAR(16) DEFAULT NULL,
                target_scope VARCHAR(16) NOT NULL,
                filter_snapshot JSONB DEFAULT NULL,
                selected_object_ids JSONB DEFAULT NULL,
                selected_columns JSONB NOT NULL DEFAULT '[]'::jsonb,
                locales JSONB DEFAULT NULL,
                channels JSONB DEFAULT NULL,
                include_variants BOOLEAN NOT NULL DEFAULT TRUE,
                target_count INT NOT NULL DEFAULT 0,
                success_count INT NOT NULL DEFAULT 0,
                file_path TEXT DEFAULT NULL,
                file_size_bytes BIGINT DEFAULT NULL,
                duration_ms INT DEFAULT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'pending',
                error_message TEXT DEFAULT NULL,
                started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                PRIMARY KEY(id)
            )
            SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX export_sessions_tenant_user_started_idx
                ON export_sessions (tenant_id, user_id, started_at DESC)
            SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX export_sessions_tenant_started_idx
                ON export_sessions (tenant_id, started_at DESC)
            SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX export_sessions_pending_running_idx
                ON export_sessions (status)
                WHERE status IN ('pending', 'running')
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE export_sessions
                ADD CONSTRAINT fk_export_sessions_tenant
                FOREIGN KEY (tenant_id) REFERENCES tenants(id)
                ON DELETE RESTRICT
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE export_sessions
                ADD CONSTRAINT fk_export_sessions_profile
                FOREIGN KEY (profile_id) REFERENCES export_profiles(id)
                ON DELETE SET NULL
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE export_logs (
                id UUID NOT NULL,
                export_session_id UUID NOT NULL,
                level VARCHAR(8) NOT NULL,
                message TEXT NOT NULL,
                context JSONB DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
            SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX export_logs_session_idx
                ON export_logs (export_session_id, created_at)
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE export_logs
                ADD CONSTRAINT fk_export_logs_session
                FOREIGN KEY (export_session_id) REFERENCES export_sessions(id)
                ON DELETE CASCADE
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS export_logs');
        $this->addSql('DROP TABLE IF EXISTS export_sessions');
        $this->addSql('DROP TABLE IF EXISTS export_profiles');
    }
}
