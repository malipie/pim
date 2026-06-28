<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * APIC-P2-02 (ADR-0022, epic APIC) — consumer-side `integration_remote_fields`
 * table: one discovered/manual field of an external API response per row, owned
 * by a {@see \App\Integration\Generic\Domain\Entity\RemoteEndpoint}.
 *
 * TenantScoped with the W1-1 FORCE RLS pattern (Version20260628100000): a
 * fail-closed tenant-isolation policy plus the super-admin break-glass bypass.
 * The FK to `integration_remote_endpoints` cascades on delete; the tenant FK is
 * RESTRICT.
 */
final class Version20260628110000 extends AbstractMigration
{
    private const string TABLE = 'integration_remote_fields';

    public function getDescription(): string
    {
        return 'APIC-P2-02: add integration_remote_fields (TenantScoped response field) + FORCE RLS policies.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE integration_remote_fields (
                id UUID NOT NULL,
                tenant_id UUID NOT NULL,
                endpoint_id UUID NOT NULL,
                path VARCHAR(512) NOT NULL,
                label VARCHAR(255) DEFAULT NULL,
                data_type VARCHAR(16) NOT NULL,
                sample_value VARCHAR(2048) DEFAULT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE INDEX IDX_2A62F69B9033212A ON integration_remote_fields (tenant_id)');
        $this->addSql('CREATE INDEX IDX_2A62F69B21AF7E36 ON integration_remote_fields (endpoint_id)');
        $this->addSql(
            'CREATE INDEX integration_remote_fields_tenant_endpoint_idx '
            .'ON integration_remote_fields (tenant_id, endpoint_id)'
        );
        $this->addSql(
            'ALTER TABLE integration_remote_fields ADD CONSTRAINT FK_2A62F69B9033212A '
            .'FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE'
        );
        $this->addSql(
            'ALTER TABLE integration_remote_fields ADD CONSTRAINT FK_2A62F69B21AF7E36 '
            .'FOREIGN KEY (endpoint_id) REFERENCES integration_remote_endpoints (id) '
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
        $this->addSql('DROP TABLE integration_remote_fields');
    }
}
