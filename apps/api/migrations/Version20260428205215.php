<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Epic 0.3 / ticket #32 — ObjectType + ObjectTypeAttribute tables.
 *
 * Per ADR-009, replaces the pre-2026-04-27 `families` + `family_attributes`
 * model with a generic `object_types` template carrying a `kind` enum
 * (`product` / `category` / `asset` / `custom`) and an `is_built_in` flag
 * for platform-owned predefined rows. The companion junction
 * `object_type_attributes` connects Attribute (#31) to ObjectType with
 * per-row `required_for_completeness` + `sort_order` and forward-compat
 * scope columns (`channel_id`, `locale`).
 *
 * `is_built_in` rows seed in #33 — Product, Category, Asset fixtures with
 * dedicated UX flow + sugar API paths (`/api/products`, `/api/categories`,
 * `/api/assets`, in #41).
 *
 * FK strategy:
 *   - object_types.tenant_id     → tenants.id    ON DELETE RESTRICT
 *   - object_types.label_attr_id → attributes.id ON DELETE SET NULL
 *   - object_types.image_attr_id → attributes.id ON DELETE SET NULL
 *   - object_type_attributes.object_type_id → object_types.id ON DELETE CASCADE
 *   - object_type_attributes.attribute_id   → attributes.id   ON DELETE RESTRICT
 *     (admin must unassign before removing an attribute used in the wild)
 *
 * RLS policies will be added in phase 2 along with the rest of the catalog
 * (#30 only covered products + refresh_tokens). MVP isolation runs on the
 * Doctrine TenantFilter via the TenantScoped contract; the junction
 * inherits scope through its parent ObjectType.
 */
final class Version20260428205215 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add object_types + object_type_attributes tables (ADR-009 — replaces families + family_attributes) (#32).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE object_types (
              id UUID NOT NULL,
              tenant_id UUID NOT NULL,
              code VARCHAR(128) NOT NULL,
              kind VARCHAR(32) NOT NULL,
              is_built_in BOOLEAN DEFAULT false NOT NULL,
              label JSONB NOT NULL,
              completeness_rules JSONB DEFAULT '{}' NOT NULL,
              label_attribute_id UUID DEFAULT NULL,
              image_attribute_id UUID DEFAULT NULL,
              schema_version INT DEFAULT 1 NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX object_types_tenant_idx ON object_types (tenant_id)');
        $this->addSql('CREATE INDEX object_types_label_attribute_idx ON object_types (label_attribute_id)');
        $this->addSql('CREATE INDEX object_types_image_attribute_idx ON object_types (image_attribute_id)');
        $this->addSql('CREATE INDEX object_types_tenant_kind_idx ON object_types (tenant_id, kind)');
        $this->addSql('CREATE UNIQUE INDEX object_types_tenant_code_uniq ON object_types (tenant_id, code)');
        $this->addSql('ALTER TABLE object_types ADD CONSTRAINT object_types_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE object_types ADD CONSTRAINT object_types_label_attribute_fk FOREIGN KEY (label_attribute_id) REFERENCES attributes (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE object_types ADD CONSTRAINT object_types_image_attribute_fk FOREIGN KEY (image_attribute_id) REFERENCES attributes (id) ON DELETE SET NULL NOT DEFERRABLE');

        $this->addSql(<<<'SQL'
            CREATE TABLE object_type_attributes (
              object_type_id UUID NOT NULL,
              attribute_id UUID NOT NULL,
              required_for_completeness BOOLEAN DEFAULT false NOT NULL,
              sort_order INT DEFAULT 0 NOT NULL,
              channel_id UUID DEFAULT NULL,
              locale VARCHAR(8) DEFAULT NULL,
              PRIMARY KEY (object_type_id, attribute_id)
            )
        SQL);
        $this->addSql('CREATE INDEX object_type_attributes_object_type_idx ON object_type_attributes (object_type_id)');
        $this->addSql('CREATE INDEX object_type_attributes_attribute_idx ON object_type_attributes (attribute_id)');
        $this->addSql('CREATE INDEX object_type_attributes_object_type_sort_idx ON object_type_attributes (object_type_id, sort_order)');
        $this->addSql('ALTER TABLE object_type_attributes ADD CONSTRAINT object_type_attributes_object_type_fk FOREIGN KEY (object_type_id) REFERENCES object_types (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE object_type_attributes ADD CONSTRAINT object_type_attributes_attribute_fk FOREIGN KEY (attribute_id) REFERENCES attributes (id) ON DELETE RESTRICT NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE object_type_attributes DROP CONSTRAINT object_type_attributes_attribute_fk');
        $this->addSql('ALTER TABLE object_type_attributes DROP CONSTRAINT object_type_attributes_object_type_fk');
        $this->addSql('DROP TABLE object_type_attributes');

        $this->addSql('ALTER TABLE object_types DROP CONSTRAINT object_types_image_attribute_fk');
        $this->addSql('ALTER TABLE object_types DROP CONSTRAINT object_types_label_attribute_fk');
        $this->addSql('ALTER TABLE object_types DROP CONSTRAINT object_types_tenant_fk');
        $this->addSql('DROP TABLE object_types');
    }
}
