<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * APIC-P2-07 (ADR-0022, epic APIC) — consumer-side `integration_field_mappings`
 * table: one 1:1 `pimTarget ↔ remoteFieldPath` mapping per row, owned by a
 * {@see \App\Integration\Generic\Domain\Entity\Connection}, versioned and
 * reusable across sync bindings (the `binding_id` link stays loose — no FK —
 * until SyncBinding lands in APIC-P3-01).
 *
 * TenantScoped with the W1-1 FORCE RLS pattern (Version20260628110000): a
 * fail-closed tenant-isolation policy plus the super-admin break-glass bypass.
 * The FK to `integration_connections` cascades on delete; the tenant FK is
 * RESTRICT.
 */
final class Version20260629100000 extends AbstractMigration
{
    private const string TABLE = 'integration_field_mappings';

    public function getDescription(): string
    {
        return 'APIC-P2-07: add integration_field_mappings (TenantScoped 1:1 mapping) + FORCE RLS policies.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE integration_field_mappings (
                id UUID NOT NULL,
                tenant_id UUID NOT NULL,
                connection_id UUID NOT NULL,
                binding_id UUID DEFAULT NULL,
                pim_target VARCHAR(255) NOT NULL,
                remote_field_path VARCHAR(512) NOT NULL,
                direction VARCHAR(16) NOT NULL,
                is_match_key BOOLEAN DEFAULT false NOT NULL,
                version INT DEFAULT 1 NOT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE INDEX IDX_73B5789033212A ON integration_field_mappings (tenant_id)');
        $this->addSql('CREATE INDEX IDX_73B578DD03F01 ON integration_field_mappings (connection_id)');
        $this->addSql(
            'CREATE INDEX integration_field_mappings_tenant_conn_idx '
            .'ON integration_field_mappings (tenant_id, connection_id)'
        );
        $this->addSql(
            'CREATE INDEX integration_field_mappings_tenant_binding_idx '
            .'ON integration_field_mappings (tenant_id, binding_id)'
        );
        $this->addSql(
            'ALTER TABLE integration_field_mappings ADD CONSTRAINT FK_73B5789033212A '
            .'FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE'
        );
        $this->addSql(
            'ALTER TABLE integration_field_mappings ADD CONSTRAINT FK_73B578DD03F01 '
            .'FOREIGN KEY (connection_id) REFERENCES integration_connections (id) '
            .'ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE'
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
        $this->addSql('DROP TABLE integration_field_mappings');
    }
}
