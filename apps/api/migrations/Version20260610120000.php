<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * EXR-04 (#1380) — generalise exports beyond products.
 *
 * Adds `entity_type` (the kind of data exported — product / custom_module /
 * module_schema / attributes_groups / categories) and `object_type_id` (the
 * target ObjectType for `custom_module`) to `export_sessions` and
 * `export_profiles`. Existing rows backfill to `product` via the NOT NULL
 * DEFAULT, preserving the product-only behaviour of the EXP epic.
 *
 * `object_type_id` is a nullable FK → `object_types` with ON DELETE SET NULL:
 * deleting a custom ObjectType detaches its export history/profiles rather
 * than cascading them away. The column is mapped as a bare uuid on the entity
 * (no Doctrine association) to keep the Export context decoupled from
 * Catalog\ObjectType — the same convention used for `user_id`.
 */
final class Version20260610120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'EXR-04 (#1380): add entity_type + object_type_id to export_sessions and export_profiles';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE export_sessions
                ADD COLUMN entity_type VARCHAR(32) NOT NULL DEFAULT 'product',
                ADD COLUMN object_type_id UUID DEFAULT NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE export_profiles
                ADD COLUMN entity_type VARCHAR(32) NOT NULL DEFAULT 'product',
                ADD COLUMN object_type_id UUID DEFAULT NULL
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE export_sessions
                ADD CONSTRAINT fk_export_sessions_object_type
                FOREIGN KEY (object_type_id) REFERENCES object_types (id)
                ON DELETE SET NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE export_profiles
                ADD CONSTRAINT fk_export_profiles_object_type
                FOREIGN KEY (object_type_id) REFERENCES object_types (id)
                ON DELETE SET NULL
        SQL);

        $this->addSql('CREATE INDEX idx_export_sessions_object_type ON export_sessions (object_type_id)');
        $this->addSql('CREATE INDEX idx_export_profiles_object_type ON export_profiles (object_type_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_export_profiles_object_type');
        $this->addSql('DROP INDEX IF EXISTS idx_export_sessions_object_type');
        $this->addSql('ALTER TABLE export_profiles DROP CONSTRAINT IF EXISTS fk_export_profiles_object_type');
        $this->addSql('ALTER TABLE export_sessions DROP CONSTRAINT IF EXISTS fk_export_sessions_object_type');
        $this->addSql('ALTER TABLE export_profiles DROP COLUMN IF EXISTS object_type_id');
        $this->addSql('ALTER TABLE export_profiles DROP COLUMN IF EXISTS entity_type');
        $this->addSql('ALTER TABLE export_sessions DROP COLUMN IF EXISTS object_type_id');
        $this->addSql('ALTER TABLE export_sessions DROP COLUMN IF EXISTS entity_type');
    }
}
