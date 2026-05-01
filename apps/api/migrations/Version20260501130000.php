<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * UI-08.6 — Attribute migrate-type backup snapshots (#261).
 *
 * Stores the pre-migration JSONB snapshot of every `object_values.value`
 * row touched by `MigrateAttributeTypeController`. The snapshot is the
 * rollback path for a destructive type migration — admin UI surfaces a
 * "Restore from backup" affordance in `#UI-08.12` (follow-up).
 *
 * Tenant scope inherited from the parent attribute; no own tenant_id
 * column. Listed in `TenantAuditCommand::INFRA_TABLES` allowlist.
 */
final class Version20260501130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Attribute migrate-type backup snapshots (#261).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE attribute_migration_backups (
              id UUID NOT NULL,
              attribute_id UUID NOT NULL,
              source_type VARCHAR(32) NOT NULL,
              target_type VARCHAR(32) NOT NULL,
              snapshot JSONB NOT NULL,
              row_count INT NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX attribute_migration_backups_attribute_idx ON attribute_migration_backups (attribute_id)');
        $this->addSql('CREATE INDEX attribute_migration_backups_created_idx ON attribute_migration_backups (created_at)');
        $this->addSql('ALTER TABLE attribute_migration_backups ADD CONSTRAINT attribute_migration_backups_attribute_fk FOREIGN KEY (attribute_id) REFERENCES attributes (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE attribute_migration_backups');
    }
}
