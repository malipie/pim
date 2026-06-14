<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * IMP2-2.2 (#1478) — `import_staged_files`: a file uploaded once at the
 * wizard's parse-preview step and reused by dry-run + start via its id, so
 * the same bytes are not re-sent three times.
 *
 * Tenant-scoped with Postgres RLS (defence in depth on top of the Doctrine
 * TenantFilter) using the `app.current_tenant` GUC set per request by the
 * TenantContextRebindingMiddleware, plus the Super Admin bypass flag.
 */
final class Version20260614160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'IMP2-2.2: import_staged_files table + tenant RLS policies';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE import_staged_files (
                id UUID NOT NULL,
                tenant_id UUID NOT NULL,
                user_id UUID NOT NULL,
                file_name VARCHAR(255) NOT NULL,
                size_bytes BIGINT NOT NULL,
                storage_key VARCHAR(1024) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
            SQL);
        $this->addSql('CREATE INDEX import_staged_files_tenant_idx ON import_staged_files (tenant_id)');
        $this->addSql('CREATE INDEX import_staged_files_tenant_user_idx ON import_staged_files (tenant_id, user_id)');
        $this->addSql('CREATE INDEX import_staged_files_created_idx ON import_staged_files (created_at)');
        $this->addSql('COMMENT ON COLUMN import_staged_files.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN import_staged_files.tenant_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN import_staged_files.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql(
            'ALTER TABLE import_staged_files ADD CONSTRAINT fk_import_staged_files_tenant '
            .'FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT NOT DEFERRABLE INITIALLY IMMEDIATE'
        );

        $this->addSql('ALTER TABLE import_staged_files ENABLE ROW LEVEL SECURITY');
        $this->addSql(
            'CREATE POLICY tenant_isolation_import_staged_files ON import_staged_files '
            ."USING (tenant_id = current_setting('app.current_tenant', true)::uuid)"
        );
        $this->addSql(
            'CREATE POLICY super_admin_bypass_import_staged_files ON import_staged_files '
            ."USING (current_setting('app.is_super_admin', true) = 'true')"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP POLICY IF EXISTS super_admin_bypass_import_staged_files ON import_staged_files');
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation_import_staged_files ON import_staged_files');
        $this->addSql('DROP TABLE import_staged_files');
    }
}
