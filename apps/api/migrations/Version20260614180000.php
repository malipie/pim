<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * IMP2-2.4 (#1480) — `import_undo_log`: before-state of every reversible change
 * an import made to a PRE-EXISTING object, so rollback v2 restores overwritten
 * values (not just deletes created objects). Created objects carry no undo rows
 * (rollback deletes them by import_session_id — D11).
 *
 * Tenant-scoped with Postgres RLS (GUC `app.current_tenant`). FK to
 * import_sessions + objects both CASCADE so the log is cleaned with its session
 * or a deleted object. Adds `import_sessions.undo_log_enabled` (default true) as
 * the substrate for the large-import opt-out (spec §3).
 */
final class Version20260614180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'IMP2-2.4: import_undo_log table + RLS + import_sessions.undo_log_enabled';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE import_sessions ADD undo_log_enabled BOOLEAN NOT NULL DEFAULT true");
        $this->addSql('ALTER TABLE import_sessions ADD rollback_report JSON DEFAULT NULL');

        $this->addSql(<<<'SQL'
            CREATE TABLE import_undo_log (
                id UUID NOT NULL,
                tenant_id UUID NOT NULL,
                import_session_id UUID NOT NULL,
                object_id UUID NOT NULL,
                operation VARCHAR(32) NOT NULL,
                attribute_code VARCHAR(128) DEFAULT NULL,
                locale VARCHAR(8) DEFAULT NULL,
                channel_id UUID DEFAULT NULL,
                payload JSON NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
            SQL);
        $this->addSql('CREATE INDEX import_undo_log_session_idx ON import_undo_log (import_session_id)');
        $this->addSql('CREATE INDEX import_undo_log_object_idx ON import_undo_log (object_id)');
        $this->addSql('COMMENT ON COLUMN import_undo_log.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN import_undo_log.tenant_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN import_undo_log.object_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN import_undo_log.channel_id IS \'(DC2Type:uuid)\'');
        $this->addSql(
            'ALTER TABLE import_undo_log ADD CONSTRAINT fk_import_undo_log_tenant '
            .'FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE'
        );
        $this->addSql(
            'ALTER TABLE import_undo_log ADD CONSTRAINT fk_import_undo_log_session '
            .'FOREIGN KEY (import_session_id) REFERENCES import_sessions (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE'
        );
        $this->addSql(
            'ALTER TABLE import_undo_log ADD CONSTRAINT fk_import_undo_log_object '
            .'FOREIGN KEY (object_id) REFERENCES objects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE'
        );

        $this->addSql('ALTER TABLE import_undo_log ENABLE ROW LEVEL SECURITY');
        $this->addSql(
            'CREATE POLICY tenant_isolation_import_undo_log ON import_undo_log '
            ."USING (tenant_id = current_setting('app.current_tenant', true)::uuid)"
        );
        $this->addSql(
            'CREATE POLICY super_admin_bypass_import_undo_log ON import_undo_log '
            ."USING (current_setting('app.is_super_admin', true) = 'true')"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP POLICY IF EXISTS super_admin_bypass_import_undo_log ON import_undo_log');
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation_import_undo_log ON import_undo_log');
        $this->addSql('DROP TABLE import_undo_log');
        $this->addSql('ALTER TABLE import_sessions DROP COLUMN undo_log_enabled');
        $this->addSql('ALTER TABLE import_sessions DROP COLUMN rollback_report');
    }
}
