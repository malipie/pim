<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * APIC-P3-01 (ADR-0022, epic APIC) — consumer-side `integration_sync_bindings`
 * table: the heart of a sync (what/where/how/how-often), owned by a
 * {@see \App\Integration\Generic\Domain\Entity\Connection}.
 *
 * Same-context FKs to the connection (CASCADE) and read/write endpoints
 * (SET NULL). `object_type_id` is a validated loose reference (no DB FK to the
 * Catalog `object_types` table) to keep cross-BC coupling at the Contracts level
 * per ADR-0022. TenantScoped with the W1-1 FORCE RLS pattern.
 */
final class Version20260629110000 extends AbstractMigration
{
    private const string TABLE = 'integration_sync_bindings';

    public function getDescription(): string
    {
        return 'APIC-P3-01: add integration_sync_bindings (TenantScoped sync binding) + FORCE RLS policies.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE integration_sync_bindings (
                id UUID NOT NULL,
                tenant_id UUID NOT NULL,
                connection_id UUID NOT NULL,
                object_type_id UUID NOT NULL,
                read_endpoint_id UUID DEFAULT NULL,
                write_endpoint_id UUID DEFAULT NULL,
                direction VARCHAR(16) NOT NULL,
                schedule VARCHAR(255) DEFAULT NULL,
                cursor JSONB DEFAULT NULL,
                conflict_policy VARCHAR(16) NOT NULL,
                match_key_mapping VARCHAR(255) DEFAULT NULL,
                enabled BOOLEAN DEFAULT true NOT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE INDEX IDX_CA56F22A9033212A ON integration_sync_bindings (tenant_id)');
        $this->addSql('CREATE INDEX IDX_CA56F22ADD03F01 ON integration_sync_bindings (connection_id)');
        $this->addSql('CREATE INDEX IDX_CA56F22A1E9BF277 ON integration_sync_bindings (read_endpoint_id)');
        $this->addSql('CREATE INDEX IDX_CA56F22A59BD8C7 ON integration_sync_bindings (write_endpoint_id)');
        $this->addSql(
            'CREATE INDEX integration_sync_bindings_tenant_conn_idx '
            .'ON integration_sync_bindings (tenant_id, connection_id)'
        );
        $this->addSql(
            'CREATE INDEX integration_sync_bindings_tenant_enabled_idx '
            .'ON integration_sync_bindings (tenant_id, enabled)'
        );
        $this->addSql(
            'ALTER TABLE integration_sync_bindings ADD CONSTRAINT FK_CA56F22A9033212A '
            .'FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE'
        );
        $this->addSql(
            'ALTER TABLE integration_sync_bindings ADD CONSTRAINT FK_CA56F22ADD03F01 '
            .'FOREIGN KEY (connection_id) REFERENCES integration_connections (id) '
            .'ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE'
        );
        $this->addSql(
            'ALTER TABLE integration_sync_bindings ADD CONSTRAINT FK_CA56F22A1E9BF277 '
            .'FOREIGN KEY (read_endpoint_id) REFERENCES integration_remote_endpoints (id) '
            .'ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE'
        );
        $this->addSql(
            'ALTER TABLE integration_sync_bindings ADD CONSTRAINT FK_CA56F22A59BD8C7 '
            .'FOREIGN KEY (write_endpoint_id) REFERENCES integration_remote_endpoints (id) '
            .'ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE'
        );

        // ── Row-Level Security (W1-1 FORCE pattern) ───────────────────────
        $predicate = "tenant_id = NULLIF(current_setting('app.current_tenant', true), '')::uuid";

        $this->addSql(\sprintf('ALTER TABLE %s ENABLE ROW LEVEL SECURITY', self::TABLE));
        $this->addSql(\sprintf('ALTER TABLE %s FORCE ROW LEVEL SECURITY', self::TABLE));
        $this->addSql(\sprintf(
            'CREATE POLICY tenant_isolation_%s ON %s USING (%s) WITH CHECK (%s)',
            self::TABLE,
            self::TABLE,
            $predicate,
            $predicate,
        ));
        $this->addSql(\sprintf(
            "CREATE POLICY super_admin_bypass_%s ON %s "
            ."USING (current_setting('app.is_super_admin', true) = 'true') "
            ."WITH CHECK (current_setting('app.is_super_admin', true) = 'true')",
            self::TABLE,
            self::TABLE,
        ));
    }

    public function down(Schema $schema): void
    {
        $this->addSql(\sprintf('DROP POLICY IF EXISTS super_admin_bypass_%s ON %s', self::TABLE, self::TABLE));
        $this->addSql(\sprintf('DROP POLICY IF EXISTS tenant_isolation_%s ON %s', self::TABLE, self::TABLE));
        $this->addSql(\sprintf('ALTER TABLE %s NO FORCE ROW LEVEL SECURITY', self::TABLE));
        $this->addSql(\sprintf('ALTER TABLE %s DISABLE ROW LEVEL SECURITY', self::TABLE));
        $this->addSql('DROP TABLE integration_sync_bindings');
    }
}
