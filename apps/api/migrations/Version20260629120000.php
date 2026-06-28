<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * APIC-P3-02 (ADR-0022, epic APIC) — sync audit tables: `integration_sync_runs`
 * (one execution of a {@see \App\Integration\Generic\Domain\Entity\SyncBinding},
 * with counters + cursor before/after) and `integration_sync_run_logs`
 * (per-record detail). Both TenantScoped with the W1-1 FORCE RLS pattern; the
 * run FK to the binding and the log FK to the run cascade on delete.
 */
final class Version20260629120000 extends AbstractMigration
{
    private const string RUNS = 'integration_sync_runs';
    private const string LOGS = 'integration_sync_run_logs';

    public function getDescription(): string
    {
        return 'APIC-P3-02: add integration_sync_runs + integration_sync_run_logs (TenantScoped audit) + FORCE RLS.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE integration_sync_runs (
                id UUID NOT NULL,
                tenant_id UUID NOT NULL,
                binding_id UUID NOT NULL,
                direction VARCHAR(16) NOT NULL,
                started_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                finished_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                status VARCHAR(16) NOT NULL,
                created_count INT DEFAULT 0 NOT NULL,
                updated_count INT DEFAULT 0 NOT NULL,
                skipped_count INT DEFAULT 0 NOT NULL,
                failed_count INT DEFAULT 0 NOT NULL,
                cursor_before JSONB DEFAULT NULL,
                cursor_after JSONB DEFAULT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE INDEX IDX_922106F39033212A ON integration_sync_runs (tenant_id)');
        $this->addSql('CREATE INDEX IDX_922106F34AC8159D ON integration_sync_runs (binding_id)');
        $this->addSql('CREATE INDEX integration_sync_runs_tenant_binding_idx ON integration_sync_runs (tenant_id, binding_id)');
        $this->addSql('CREATE INDEX integration_sync_runs_tenant_started_idx ON integration_sync_runs (tenant_id, started_at)');
        $this->addSql(
            'ALTER TABLE integration_sync_runs ADD CONSTRAINT FK_922106F39033212A '
            .'FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE'
        );
        $this->addSql(
            'ALTER TABLE integration_sync_runs ADD CONSTRAINT FK_922106F34AC8159D '
            .'FOREIGN KEY (binding_id) REFERENCES integration_sync_bindings (id) '
            .'ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE'
        );

        $this->addSql(<<<'SQL'
            CREATE TABLE integration_sync_run_logs (
                id UUID NOT NULL,
                tenant_id UUID NOT NULL,
                run_id UUID NOT NULL,
                match_key VARCHAR(255) DEFAULT NULL,
                action VARCHAR(16) NOT NULL,
                fields JSONB DEFAULT NULL,
                message TEXT DEFAULT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE INDEX IDX_F67E26299033212A ON integration_sync_run_logs (tenant_id)');
        $this->addSql('CREATE INDEX IDX_F67E262984E3FEC4 ON integration_sync_run_logs (run_id)');
        $this->addSql('CREATE INDEX integration_sync_run_logs_tenant_run_idx ON integration_sync_run_logs (tenant_id, run_id)');
        $this->addSql(
            'ALTER TABLE integration_sync_run_logs ADD CONSTRAINT FK_F67E26299033212A '
            .'FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE'
        );
        $this->addSql(
            'ALTER TABLE integration_sync_run_logs ADD CONSTRAINT FK_F67E262984E3FEC4 '
            .'FOREIGN KEY (run_id) REFERENCES integration_sync_runs (id) '
            .'ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE'
        );

        foreach ([self::RUNS, self::LOGS] as $table) {
            $this->enableRls($table);
        }
    }

    public function down(Schema $schema): void
    {
        foreach ([self::LOGS, self::RUNS] as $table) {
            $this->addSql(\sprintf('DROP POLICY IF EXISTS super_admin_bypass_%s ON %s', $table, $table));
            $this->addSql(\sprintf('DROP POLICY IF EXISTS tenant_isolation_%s ON %s', $table, $table));
            $this->addSql(\sprintf('ALTER TABLE %s NO FORCE ROW LEVEL SECURITY', $table));
            $this->addSql(\sprintf('ALTER TABLE %s DISABLE ROW LEVEL SECURITY', $table));
        }
        $this->addSql('DROP TABLE integration_sync_run_logs');
        $this->addSql('DROP TABLE integration_sync_runs');
    }

    private function enableRls(string $table): void
    {
        $predicate = "tenant_id = NULLIF(current_setting('app.current_tenant', true), '')::uuid";

        $this->addSql(\sprintf('ALTER TABLE %s ENABLE ROW LEVEL SECURITY', $table));
        $this->addSql(\sprintf('ALTER TABLE %s FORCE ROW LEVEL SECURITY', $table));
        $this->addSql(\sprintf(
            'CREATE POLICY tenant_isolation_%s ON %s USING (%s) WITH CHECK (%s)',
            $table,
            $table,
            $predicate,
            $predicate,
        ));
        $this->addSql(\sprintf(
            "CREATE POLICY super_admin_bypass_%s ON %s "
            ."USING (current_setting('app.is_super_admin', true) = 'true') "
            ."WITH CHECK (current_setting('app.is_super_admin', true) = 'true')",
            $table,
            $table,
        ));
    }
}
