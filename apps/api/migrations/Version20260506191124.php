<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * IMP-01 (#442) — Imports MVP schema bootstrap.
 *
 * Adds five things in one shot so the rest of epik UI-09 / 0.13 can land
 * incrementally without further migrations until IMP-04+:
 *   1. `import_profiles` — per-user saved wizard config (mapping + smart memory).
 *   2. `backups` — pgBackRest snapshot state machine (IMP-06 wraps the CLI).
 *   3. `import_sessions` — one row per upload, audit + counts + rollback window.
 *   4. `import_logs` — per-row trace feeding validation preview, progress
 *      stream, and the post-import CSV report. FK to sessions cascades.
 *   5. `objects.import_session_id` — links every imported CatalogObject to
 *      the session that created it; the partial index keeps the rollback
 *      query (`WHERE import_session_id = X`) fast without bloating the
 *      catalog index footprint.
 *
 * Order matters in `up()`: profiles + backups first because import_sessions
 * has nullable FKs to both. `down()` reverses, dropping the column on
 * objects before tearing down the parent tables.
 */
final class Version20260506191124 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'IMP-01: imports + backups schema (sessions, profiles, logs, backups, objects.import_session_id).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE import_profiles (
                id UUID NOT NULL,
                tenant_id UUID NOT NULL,
                user_id UUID NOT NULL,
                target_object_type_id UUID NOT NULL,
                name VARCHAR(255) NOT NULL,
                column_mapping JSONB NOT NULL DEFAULT '{}'::jsonb,
                locale VARCHAR(8) DEFAULT NULL,
                encoding VARCHAR(32) DEFAULT NULL,
                delimiter VARCHAR(4) DEFAULT NULL,
                image_source VARCHAR(16) NOT NULL DEFAULT 'none',
                image_zip_naming_convention VARCHAR(64) DEFAULT NULL,
                custom_validation_rules JSONB DEFAULT NULL,
                last_used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX import_profiles_tenant_user_name_uniq ON import_profiles (tenant_id, user_id, name)');
        $this->addSql('CREATE INDEX import_profiles_tenant_user_idx ON import_profiles (tenant_id, user_id)');
        $this->addSql('ALTER TABLE import_profiles ADD CONSTRAINT import_profiles_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE import_profiles ADD CONSTRAINT import_profiles_object_type_fk FOREIGN KEY (target_object_type_id) REFERENCES object_types (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql(<<<'SQL'
            CREATE TABLE backups (
                id UUID NOT NULL,
                tenant_id UUID NOT NULL,
                triggered_by_user_id UUID NOT NULL,
                triggered_by_action VARCHAR(32) NOT NULL,
                pgbackrest_label VARCHAR(255) DEFAULT NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'pending',
                size_bytes BIGINT DEFAULT NULL,
                started_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                error_message TEXT DEFAULT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX backups_tenant_idx ON backups (tenant_id)');
        $this->addSql('CREATE INDEX backups_tenant_status_idx ON backups (tenant_id, status)');
        $this->addSql('CREATE INDEX backups_tenant_started_idx ON backups (tenant_id, started_at)');
        $this->addSql('ALTER TABLE backups ADD CONSTRAINT backups_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql(<<<'SQL'
            CREATE TABLE import_sessions (
                id UUID NOT NULL,
                tenant_id UUID NOT NULL,
                user_id UUID NOT NULL,
                profile_id UUID DEFAULT NULL,
                target_object_type_id UUID NOT NULL,
                backup_snapshot_id UUID DEFAULT NULL,
                file_name VARCHAR(255) NOT NULL,
                file_size_bytes BIGINT NOT NULL,
                zip_file_name VARCHAR(255) DEFAULT NULL,
                zip_file_size_bytes BIGINT DEFAULT NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'pending',
                total_rows INT DEFAULT NULL,
                success_count INT NOT NULL DEFAULT 0,
                error_count INT NOT NULL DEFAULT 0,
                images_downloaded INT NOT NULL DEFAULT 0,
                images_failed INT NOT NULL DEFAULT 0,
                started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                completed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                rollback_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                rolled_back_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                error_message TEXT DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX import_sessions_tenant_idx ON import_sessions (tenant_id)');
        $this->addSql('CREATE INDEX import_sessions_tenant_user_created_idx ON import_sessions (tenant_id, user_id, created_at)');
        $this->addSql('CREATE INDEX import_sessions_tenant_status_idx ON import_sessions (tenant_id, status)');
        $this->addSql('ALTER TABLE import_sessions ADD CONSTRAINT import_sessions_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE import_sessions ADD CONSTRAINT import_sessions_object_type_fk FOREIGN KEY (target_object_type_id) REFERENCES object_types (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE import_sessions ADD CONSTRAINT import_sessions_profile_fk FOREIGN KEY (profile_id) REFERENCES import_profiles (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE import_sessions ADD CONSTRAINT import_sessions_backup_fk FOREIGN KEY (backup_snapshot_id) REFERENCES backups (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql(<<<'SQL'
            CREATE TABLE import_logs (
                id UUID NOT NULL,
                import_session_id UUID NOT NULL,
                row_number INT NOT NULL,
                sku VARCHAR(128) DEFAULT NULL,
                level VARCHAR(8) NOT NULL,
                error_type VARCHAR(32) DEFAULT NULL,
                message TEXT NOT NULL,
                column_name VARCHAR(128) DEFAULT NULL,
                column_value TEXT DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX import_logs_session_idx ON import_logs (import_session_id, row_number)');
        $this->addSql('CREATE INDEX import_logs_session_level_idx ON import_logs (import_session_id, level)');
        $this->addSql('ALTER TABLE import_logs ADD CONSTRAINT import_logs_session_fk FOREIGN KEY (import_session_id) REFERENCES import_sessions (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('ALTER TABLE objects ADD COLUMN import_session_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE objects ADD CONSTRAINT objects_import_session_fk FOREIGN KEY (import_session_id) REFERENCES import_sessions (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql(<<<'SQL'
            CREATE INDEX objects_import_session_idx
              ON objects (import_session_id)
              WHERE import_session_id IS NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS objects_import_session_idx');
        $this->addSql('ALTER TABLE objects DROP CONSTRAINT IF EXISTS objects_import_session_fk');
        $this->addSql('ALTER TABLE objects DROP COLUMN IF EXISTS import_session_id');

        $this->addSql('ALTER TABLE import_logs DROP CONSTRAINT IF EXISTS import_logs_session_fk');
        $this->addSql('DROP TABLE IF EXISTS import_logs');

        $this->addSql('ALTER TABLE import_sessions DROP CONSTRAINT IF EXISTS import_sessions_backup_fk');
        $this->addSql('ALTER TABLE import_sessions DROP CONSTRAINT IF EXISTS import_sessions_profile_fk');
        $this->addSql('ALTER TABLE import_sessions DROP CONSTRAINT IF EXISTS import_sessions_object_type_fk');
        $this->addSql('ALTER TABLE import_sessions DROP CONSTRAINT IF EXISTS import_sessions_tenant_fk');
        $this->addSql('DROP TABLE IF EXISTS import_sessions');

        $this->addSql('ALTER TABLE backups DROP CONSTRAINT IF EXISTS backups_tenant_fk');
        $this->addSql('DROP TABLE IF EXISTS backups');

        $this->addSql('ALTER TABLE import_profiles DROP CONSTRAINT IF EXISTS import_profiles_object_type_fk');
        $this->addSql('ALTER TABLE import_profiles DROP CONSTRAINT IF EXISTS import_profiles_tenant_fk');
        $this->addSql('DROP TABLE IF EXISTS import_profiles');
    }
}
