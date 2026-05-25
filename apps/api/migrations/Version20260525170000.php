<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * ULV-01 (#982) — schema foundation for the universal ObjectType list view.
 *
 * - `object_type_attributes.show_in_list` (BOOL) + `list_position` (INT)
 *   drive which attributes render as columns in the universal list and in
 *   what order. Defaults: false / 0 so existing junctions stay column-less.
 * - `saved_views.object_type_id` (UUID NULL, FK) scopes a saved view to a
 *   specific ObjectType. Nullable for backward compatibility with the
 *   pre-ULV `resource` string column; ULV-06 / ULV-11 finish the cutover.
 *
 * No `object_types.slug` migration: per ADR-009 sugar-paths and ULV-01
 * scope discussion, `object_types.code` already satisfies the URL-safe
 * identifier requirement (unique per tenant, lowercase by convention) and
 * is reused for routing `/objects/{code}` rather than duplicating data.
 */
final class Version20260525170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ULV-01 (#982): list-column flags on object_type_attributes + object_type_id on saved_views.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE object_type_attributes
                ADD COLUMN show_in_list BOOLEAN NOT NULL DEFAULT FALSE
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE object_type_attributes
                ADD COLUMN list_position INTEGER NOT NULL DEFAULT 0
        SQL);

        $this->addSql(<<<'SQL'
            CREATE INDEX object_type_attributes_show_in_list_idx
                ON object_type_attributes (object_type_id, show_in_list, list_position)
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE saved_views
                ADD COLUMN object_type_id UUID NULL
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE saved_views
                ADD CONSTRAINT fk_saved_views_object_type
                FOREIGN KEY (object_type_id) REFERENCES object_types (id)
                ON DELETE CASCADE
        SQL);

        $this->addSql(<<<'SQL'
            CREATE INDEX saved_views_tenant_object_type_idx
                ON saved_views (tenant_id, object_type_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DROP INDEX IF EXISTS saved_views_tenant_object_type_idx
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE saved_views
                DROP CONSTRAINT IF EXISTS fk_saved_views_object_type
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE saved_views DROP COLUMN object_type_id
        SQL);

        $this->addSql(<<<'SQL'
            DROP INDEX IF EXISTS object_type_attributes_show_in_list_idx
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE object_type_attributes DROP COLUMN list_position
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE object_type_attributes DROP COLUMN show_in_list
        SQL);
    }
}
