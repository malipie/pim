<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * APIC-P1-01 (ADR-0022, epic APIC) — consumer-side `integration_connections`
 * table: one external REST/JSON API connection per row, TenantScoped with
 * Postgres RLS.
 *
 * Credentials live in two columns (`credentials_ciphertext` + key version),
 * mirroring `tenant_agent_configs` / ADR-0017; the reversible-encryption write
 * path + response masking land in APIC-P1-02.
 *
 * RLS follows the W1-1 FORCE pattern (Version20260617050000): a strict
 * (fail-closed) tenant-isolation policy plus the super-admin break-glass
 * bypass. Connections always run with a resolved tenant, so no pre-context
 * relaxation is needed. The `NULLIF(current_setting(...), '')::uuid` cast keeps
 * an empty GUC from raising `invalid input syntax for type uuid: ""`.
 */
final class Version20260627100000 extends AbstractMigration
{
    private const string TABLE = 'integration_connections';

    public function getDescription(): string
    {
        return 'APIC-P1-01: add integration_connections (TenantScoped consumer connection) + FORCE RLS policies.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE integration_connections (
                id UUID NOT NULL,
                tenant_id UUID NOT NULL,
                code VARCHAR(64) NOT NULL,
                name VARCHAR(255) NOT NULL,
                base_url VARCHAR(2048) NOT NULL,
                auth_type VARCHAR(16) DEFAULT 'none' NOT NULL,
                credentials_ciphertext TEXT DEFAULT NULL,
                credentials_key_version INT DEFAULT NULL,
                default_headers JSONB DEFAULT '{}' NOT NULL,
                rate_limit_hint INT DEFAULT NULL,
                status VARCHAR(16) DEFAULT 'draft' NOT NULL,
                last_health_check_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE INDEX IDX_CB08B1B09033212A ON integration_connections (tenant_id)');
        $this->addSql('CREATE INDEX integration_connections_tenant_status_idx ON integration_connections (tenant_id, status)');
        $this->addSql('CREATE UNIQUE INDEX integration_connections_tenant_code_uniq ON integration_connections (tenant_id, code)');
        $this->addSql(
            'ALTER TABLE integration_connections ADD CONSTRAINT FK_CB08B1B09033212A '
            .'FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE'
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
        $this->addSql('DROP TABLE integration_connections');
    }
}
