<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * VIEW-IMP-04 (#502) — `import_schedules` + `import_schedule_runs`.
 *
 * The cron worker daemon ships in the follow-up — V04 only registers
 * the schema, the CRUD endpoints, and the manual `run-now` trigger.
 * The runs table starts mostly empty until the dispatcher kicks in.
 */
final class Version20260512200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'VIEW-IMP-04: import_schedules + import_schedule_runs.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
CREATE TABLE import_schedules (
    id UUID NOT NULL,
    tenant_id UUID NOT NULL,
    user_id UUID NOT NULL,
    source_id UUID DEFAULT NULL,
    profile_id UUID DEFAULT NULL,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(64) NOT NULL,
    cron VARCHAR(64) NOT NULL,
    cron_human VARCHAR(255) DEFAULT NULL,
    priority VARCHAR(8) NOT NULL DEFAULT 'normal',
    enabled BOOLEAN NOT NULL DEFAULT TRUE,
    next_run TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
    last_run_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
    last_run_status VARCHAR(16) DEFAULT NULL,
    last_run_duration_ms INTEGER DEFAULT NULL,
    notify_channels JSONB NOT NULL DEFAULT '[]'::jsonb,
    notify_config JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
    updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE UNIQUE INDEX import_schedules_tenant_code_uniq ON import_schedules (tenant_id, code)');
        $this->addSql('CREATE INDEX import_schedules_tenant_enabled_next_idx ON import_schedules (tenant_id, enabled, next_run)');
        $this->addSql(<<<'SQL'
ALTER TABLE import_schedules
    ADD CONSTRAINT FK_import_schedules_tenant FOREIGN KEY (tenant_id)
        REFERENCES tenants(id) ON DELETE RESTRICT
SQL);
        $this->addSql(<<<'SQL'
ALTER TABLE import_schedules
    ADD CONSTRAINT FK_import_schedules_source FOREIGN KEY (source_id)
        REFERENCES import_sources(id) ON DELETE SET NULL
SQL);
        $this->addSql(<<<'SQL'
ALTER TABLE import_schedules
    ADD CONSTRAINT FK_import_schedules_profile FOREIGN KEY (profile_id)
        REFERENCES import_profiles(id) ON DELETE SET NULL
SQL);

        $this->addSql(<<<'SQL'
CREATE TABLE import_schedule_runs (
    id UUID NOT NULL,
    schedule_id UUID NOT NULL,
    tenant_id UUID NOT NULL,
    triggered_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
    status VARCHAR(16) NOT NULL,
    duration_ms INTEGER DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    session_id UUID DEFAULT NULL,
    PRIMARY KEY(id)
)
SQL);
        $this->addSql('CREATE INDEX import_schedule_runs_schedule_idx ON import_schedule_runs (schedule_id, triggered_at DESC)');
        $this->addSql(<<<'SQL'
ALTER TABLE import_schedule_runs
    ADD CONSTRAINT FK_import_schedule_runs_schedule FOREIGN KEY (schedule_id)
        REFERENCES import_schedules(id) ON DELETE CASCADE
SQL);
        $this->addSql(<<<'SQL'
ALTER TABLE import_schedule_runs
    ADD CONSTRAINT FK_import_schedule_runs_tenant FOREIGN KEY (tenant_id)
        REFERENCES tenants(id) ON DELETE RESTRICT
SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS import_schedule_runs');
        $this->addSql('DROP TABLE IF EXISTS import_schedules');
    }
}
