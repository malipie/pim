<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * IMP2-2.5 (#1481) — give `import_logs` its own `tenant_id` + Postgres RLS so
 * tenant isolation no longer rides only on the FK to `import_sessions`. This
 * closes the last import table that would break once FORCE RLS lands before
 * the first multi-tenant deployment (RBAC Phase 2 #654 follow-up).
 *
 * The column is added nullable, backfilled from the parent session (every log
 * has a session via FK CASCADE, every session a NOT NULL tenant), then locked
 * NOT NULL — the SET NOT NULL is itself the "0 NULL rows" assertion (it fails
 * loudly if the backfill missed any). RLS mirrors Version20260518170000.
 */
final class Version20260614190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'IMP2-2.5: import_logs.tenant_id (backfilled from session) + RLS policies';
    }

    public function up(Schema $schema): void
    {
        // 1. Add nullable, backfill from the parent session, then lock NOT NULL.
        $this->addSql('ALTER TABLE import_logs ADD tenant_id UUID DEFAULT NULL');
        $this->addSql(<<<'SQL'
            UPDATE import_logs l
            SET tenant_id = s.tenant_id
            FROM import_sessions s
            WHERE s.id = l.import_session_id
            SQL);
        // Total backfill: SET NOT NULL throws if a single row stayed NULL.
        $this->addSql('ALTER TABLE import_logs ALTER COLUMN tenant_id SET NOT NULL');
        $this->addSql('COMMENT ON COLUMN import_logs.tenant_id IS \'(DC2Type:uuid)\'');
        $this->addSql(
            'ALTER TABLE import_logs ADD CONSTRAINT fk_import_logs_tenant '
            .'FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE'
        );
        $this->addSql('CREATE INDEX import_logs_tenant_idx ON import_logs (tenant_id)');

        // 2. RLS — direct tenant isolation, defence in depth alongside TenantFilter.
        $this->addSql('ALTER TABLE import_logs ENABLE ROW LEVEL SECURITY');
        $this->addSql(
            'CREATE POLICY tenant_isolation_import_logs ON import_logs '
            ."USING (tenant_id = current_setting('app.current_tenant', true)::uuid)"
        );
        $this->addSql(
            'CREATE POLICY super_admin_bypass_import_logs ON import_logs '
            ."USING (current_setting('app.is_super_admin', true) = 'true')"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP POLICY IF EXISTS super_admin_bypass_import_logs ON import_logs');
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation_import_logs ON import_logs');
        $this->addSql('ALTER TABLE import_logs DISABLE ROW LEVEL SECURITY');
        $this->addSql('DROP INDEX import_logs_tenant_idx');
        $this->addSql('ALTER TABLE import_logs DROP CONSTRAINT fk_import_logs_tenant');
        $this->addSql('ALTER TABLE import_logs DROP tenant_id');
    }
}
