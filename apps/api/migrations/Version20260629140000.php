<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * APIC-P4-05 (ADR-0022, epic APIC) — add `api_webhook_deliveries`: the audit
 * trail of producer webhook deliveries (event, target, payload, status,
 * attempts, last error, timing). TenantScoped with the W1-1 FORCE RLS pattern;
 * `profile_id` is a same-context loose reference (no FK so a profile delete
 * keeps the delivery history).
 */
final class Version20260629140000 extends AbstractMigration
{
    private const string TABLE = 'api_webhook_deliveries';

    public function getDescription(): string
    {
        return 'APIC-P4-05: add api_webhook_deliveries (webhook delivery audit) + FORCE RLS.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE api_webhook_deliveries (
                id UUID NOT NULL,
                tenant_id UUID NOT NULL,
                profile_id UUID NOT NULL,
                event_type VARCHAR(128) NOT NULL,
                target_url VARCHAR(2048) NOT NULL,
                payload JSONB NOT NULL,
                status VARCHAR(16) NOT NULL,
                attempts INT DEFAULT 0 NOT NULL,
                http_status INT DEFAULT NULL,
                duration_ms INT DEFAULT NULL,
                last_error TEXT DEFAULT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        $this->addSql('CREATE INDEX api_webhook_deliveries_tenant_idx ON api_webhook_deliveries (tenant_id)');
        $this->addSql('CREATE INDEX api_webhook_deliveries_tenant_profile_idx ON api_webhook_deliveries (tenant_id, profile_id)');
        $this->addSql('CREATE INDEX api_webhook_deliveries_tenant_created_idx ON api_webhook_deliveries (tenant_id, created_at)');
        $this->addSql(
            'ALTER TABLE api_webhook_deliveries ADD CONSTRAINT FK_api_webhook_deliveries_tenant '
            .'FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE'
        );

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
        $this->addSql('DROP TABLE api_webhook_deliveries');
    }
}
