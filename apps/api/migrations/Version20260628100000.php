<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * APIC-P2-01 (ADR-0022, epic APIC) — consumer-side `integration_remote_endpoints`
 * table: one operation descriptor (read_list/read_one/write_create/write_update)
 * per row, owned by a {@see \App\Integration\Generic\Domain\Entity\Connection}.
 *
 * TenantScoped with the W1-1 FORCE RLS pattern (Version20260627100000): a
 * fail-closed tenant-isolation policy plus the super-admin break-glass bypass.
 * The FK to `integration_connections` cascades on delete, so removing a
 * connection drops its descriptor; the tenant FK is RESTRICT.
 */
final class Version20260628100000 extends AbstractMigration
{
    private const string TABLE = 'integration_remote_endpoints';

    public function getDescription(): string
    {
        return 'APIC-P2-01: add integration_remote_endpoints (TenantScoped operation descriptor) + FORCE RLS policies.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE integration_remote_endpoints (
                id UUID NOT NULL,
                tenant_id UUID NOT NULL,
                connection_id UUID NOT NULL,
                role VARCHAR(16) NOT NULL,
                http_method VARCHAR(8) NOT NULL,
                path_template VARCHAR(2048) NOT NULL,
                query_params JSONB DEFAULT '{}' NOT NULL,
                request_body_template JSONB DEFAULT NULL,
                pagination JSONB NOT NULL,
                record_selector VARCHAR(512) DEFAULT NULL,
                response_format VARCHAR(16) DEFAULT 'json' NOT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE INDEX IDX_8614C85A9033212A ON integration_remote_endpoints (tenant_id)');
        $this->addSql('CREATE INDEX IDX_8614C85ADD03F01 ON integration_remote_endpoints (connection_id)');
        $this->addSql(
            'CREATE INDEX integration_remote_endpoints_tenant_conn_idx '
            .'ON integration_remote_endpoints (tenant_id, connection_id)'
        );
        $this->addSql(
            'ALTER TABLE integration_remote_endpoints ADD CONSTRAINT FK_8614C85A9033212A '
            .'FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE'
        );
        $this->addSql(
            'ALTER TABLE integration_remote_endpoints ADD CONSTRAINT FK_8614C85ADD03F01 '
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
        $this->addSql('DROP TABLE integration_remote_endpoints');
    }
}
