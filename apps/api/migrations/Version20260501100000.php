<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Epic 0.12 / UI-08.1 — AttributeGroup as first-class entity (ADR-012).
 *
 * Extends `attribute_groups` with description/icon/color/is_system_group/auto_attached,
 * adds three junction tables that wire AttributeGroup into the catalog: M:N with
 * Attribute (replaces Attribute.group_id 1:N — both paths coexist for now), M:N
 * with ObjectType (global per-type groups), and M:N with Category × ObjectType
 * (groups inherited down the category tree for objects of a given kind).
 *
 * Existing `Attribute.group_id` 1:N path is deliberately untouched in this
 * migration — data migration to attribute_group_attributes lands after #UI-08.5
 * when admin UI handles multi-group attachment. The two paths are both valid
 * during the transition.
 *
 * See ADR-012 in Project Plan/01-architektura-pim.md and the full schema spec
 * in Project Plan/UI/epik-08-modelowanie.md §3.8 / §12.2.
 */
final class Version20260501100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Promote AttributeGroup to first-class entity per ADR-012 (#256 / UI-08.1).';
    }

    public function up(Schema $schema): void
    {
        // Extend attribute_groups with first-class metadata.
        $this->addSql('ALTER TABLE attribute_groups ADD COLUMN description JSONB DEFAULT NULL');
        $this->addSql("ALTER TABLE attribute_groups ADD COLUMN icon VARCHAR(64) DEFAULT NULL");
        $this->addSql("ALTER TABLE attribute_groups ADD COLUMN color VARCHAR(16) DEFAULT NULL");
        $this->addSql('ALTER TABLE attribute_groups ADD COLUMN is_system_group BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE attribute_groups ADD COLUMN auto_attached BOOLEAN NOT NULL DEFAULT false');

        // M:N Attribute ↔ AttributeGroup. Coexists with Attribute.group_id 1:N for now.
        $this->addSql(<<<'SQL'
            CREATE TABLE attribute_group_attributes (
              attribute_group_id UUID NOT NULL,
              attribute_id UUID NOT NULL,
              position INT NOT NULL DEFAULT 0,
              is_required_in_group BOOLEAN NOT NULL DEFAULT false,
              visible_when JSONB DEFAULT NULL,
              PRIMARY KEY (attribute_group_id, attribute_id)
            )
        SQL);
        $this->addSql('CREATE INDEX attribute_group_attributes_group_idx ON attribute_group_attributes (attribute_group_id)');
        $this->addSql('CREATE INDEX attribute_group_attributes_attribute_idx ON attribute_group_attributes (attribute_id)');
        $this->addSql('ALTER TABLE attribute_group_attributes ADD CONSTRAINT attribute_group_attributes_group_fk FOREIGN KEY (attribute_group_id) REFERENCES attribute_groups (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE attribute_group_attributes ADD CONSTRAINT attribute_group_attributes_attribute_fk FOREIGN KEY (attribute_id) REFERENCES attributes (id) ON DELETE CASCADE NOT DEFERRABLE');

        // M:N ObjectType ↔ AttributeGroup — globalne grupy dla typu.
        $this->addSql(<<<'SQL'
            CREATE TABLE object_type_attribute_groups (
              object_type_id UUID NOT NULL,
              attribute_group_id UUID NOT NULL,
              position INT NOT NULL DEFAULT 0,
              PRIMARY KEY (object_type_id, attribute_group_id)
            )
        SQL);
        $this->addSql('CREATE INDEX object_type_attribute_groups_type_idx ON object_type_attribute_groups (object_type_id)');
        $this->addSql('CREATE INDEX object_type_attribute_groups_group_idx ON object_type_attribute_groups (attribute_group_id)');
        $this->addSql('ALTER TABLE object_type_attribute_groups ADD CONSTRAINT object_type_attribute_groups_type_fk FOREIGN KEY (object_type_id) REFERENCES object_types (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE object_type_attribute_groups ADD CONSTRAINT object_type_attribute_groups_group_fk FOREIGN KEY (attribute_group_id) REFERENCES attribute_groups (id) ON DELETE CASCADE NOT DEFERRABLE');

        // M:N Category × ObjectType → AttributeGroup. category_object_id points
        // at the catalog_objects row that IS the category (kind='category' per
        // ADR-009); target_object_type_id is which kind of objects under this
        // category will inherit the group.
        $this->addSql(<<<'SQL'
            CREATE TABLE category_attribute_groups (
              category_object_id UUID NOT NULL,
              target_object_type_id UUID NOT NULL,
              attribute_group_id UUID NOT NULL,
              position INT NOT NULL DEFAULT 0,
              PRIMARY KEY (category_object_id, target_object_type_id, attribute_group_id)
            )
        SQL);
        $this->addSql('CREATE INDEX category_attribute_groups_category_idx ON category_attribute_groups (category_object_id)');
        $this->addSql('CREATE INDEX category_attribute_groups_target_type_idx ON category_attribute_groups (target_object_type_id)');
        $this->addSql('CREATE INDEX category_attribute_groups_group_idx ON category_attribute_groups (attribute_group_id)');
        $this->addSql('ALTER TABLE category_attribute_groups ADD CONSTRAINT category_attribute_groups_category_fk FOREIGN KEY (category_object_id) REFERENCES objects (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE category_attribute_groups ADD CONSTRAINT category_attribute_groups_target_type_fk FOREIGN KEY (target_object_type_id) REFERENCES object_types (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE category_attribute_groups ADD CONSTRAINT category_attribute_groups_group_fk FOREIGN KEY (attribute_group_id) REFERENCES attribute_groups (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE category_attribute_groups');
        $this->addSql('DROP TABLE object_type_attribute_groups');
        $this->addSql('DROP TABLE attribute_group_attributes');
        $this->addSql('ALTER TABLE attribute_groups DROP COLUMN auto_attached');
        $this->addSql('ALTER TABLE attribute_groups DROP COLUMN is_system_group');
        $this->addSql('ALTER TABLE attribute_groups DROP COLUMN color');
        $this->addSql('ALTER TABLE attribute_groups DROP COLUMN icon');
        $this->addSql('ALTER TABLE attribute_groups DROP COLUMN description');
    }
}
