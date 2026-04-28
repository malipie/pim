<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Epic 0.3 / ticket #31 — Attribute, AttributeGroup, AttributeOption tables.
 *
 * Sets up the catalog attribute model (per ADR-006 hybrid + ADR-009 generic
 * ObjectType). Three tables, all tenant-scoped:
 *
 *   - `attribute_groups`: organisational containers with localised label.
 *   - `attributes`: typed attribute definitions, one type per row from the
 *     ten-case AttributeType enum (`text`, `number`, `select`, `multiselect`,
 *     `date`, `boolean`, `asset`, `relation`, `price`, `metric`).
 *   - `attribute_options`: choices for `select` / `multiselect` attributes;
 *     other types do not carry option rows. The invariant is enforced at
 *     the validator layer (#39), not at the schema, to keep bulk inserts
 *     fast.
 *
 * `label` and `help` columns are JSONB so each row carries every supported
 * locale (`{pl, en}` in MVP). `validation_rules` is JSONB and stored
 * verbatim — the per-type interpreter that enforces constraints lands in
 * #39 (0.3.9). Until then, admins can configure rules but the server does
 * not yet trip on out-of-range values.
 *
 * FK strategy:
 *   - `*.tenant_id → tenants.id` ON DELETE RESTRICT — protects against
 *     accidental drop of a tenant whose catalog still references it.
 *   - `attributes.group_id → attribute_groups.id` ON DELETE SET NULL —
 *     removing a group leaves its attributes alive but ungrouped.
 *   - `attribute_options.attribute_id → attributes.id` ON DELETE CASCADE —
 *     deleting an attribute removes its options.
 *
 * RLS policies for these tables are intentionally NOT created here.
 * Migration `Version20260428195217` (#30) only covered `products` +
 * `refresh_tokens`. Application-layer isolation via `TenantFilter` (the
 * three entities implement TenantScoped) is sufficient for MVP; phase 2
 * will land a separate migration that adds policies on every catalog
 * table at once.
 */
final class Version20260428202805 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add attribute_groups, attributes, attribute_options tables for the catalog attribute model (#31).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE attribute_groups (
              id UUID NOT NULL,
              tenant_id UUID NOT NULL,
              code VARCHAR(64) NOT NULL,
              label JSONB NOT NULL,
              position INT DEFAULT 0 NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX attribute_groups_tenant_idx ON attribute_groups (tenant_id)');
        $this->addSql('CREATE INDEX attribute_groups_tenant_position_idx ON attribute_groups (tenant_id, position)');
        $this->addSql('CREATE UNIQUE INDEX attribute_groups_tenant_code_uniq ON attribute_groups (tenant_id, code)');
        $this->addSql('ALTER TABLE attribute_groups ADD CONSTRAINT attribute_groups_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT NOT DEFERRABLE');

        $this->addSql(<<<'SQL'
            CREATE TABLE attributes (
              id UUID NOT NULL,
              tenant_id UUID NOT NULL,
              group_id UUID DEFAULT NULL,
              code VARCHAR(64) NOT NULL,
              label JSONB NOT NULL,
              help JSONB DEFAULT NULL,
              type VARCHAR(32) NOT NULL,
              is_localizable BOOLEAN DEFAULT false NOT NULL,
              is_scopable BOOLEAN DEFAULT false NOT NULL,
              is_required BOOLEAN DEFAULT false NOT NULL,
              validation_rules JSONB DEFAULT '{}' NOT NULL,
              position INT DEFAULT 0 NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX attributes_tenant_idx ON attributes (tenant_id)');
        $this->addSql('CREATE INDEX attributes_group_idx ON attributes (group_id)');
        $this->addSql('CREATE INDEX attributes_tenant_group_position_idx ON attributes (tenant_id, group_id, position)');
        $this->addSql('CREATE UNIQUE INDEX attributes_tenant_code_uniq ON attributes (tenant_id, code)');
        $this->addSql('ALTER TABLE attributes ADD CONSTRAINT attributes_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE attributes ADD CONSTRAINT attributes_group_fk FOREIGN KEY (group_id) REFERENCES attribute_groups (id) ON DELETE SET NULL NOT DEFERRABLE');

        $this->addSql(<<<'SQL'
            CREATE TABLE attribute_options (
              id UUID NOT NULL,
              tenant_id UUID NOT NULL,
              attribute_id UUID NOT NULL,
              code VARCHAR(64) NOT NULL,
              label JSONB NOT NULL,
              position INT DEFAULT 0 NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX attribute_options_tenant_idx ON attribute_options (tenant_id)');
        $this->addSql('CREATE INDEX attribute_options_attribute_idx ON attribute_options (attribute_id)');
        $this->addSql('CREATE INDEX attribute_options_attribute_position_idx ON attribute_options (attribute_id, position)');
        $this->addSql('CREATE UNIQUE INDEX attribute_options_attribute_code_uniq ON attribute_options (attribute_id, code)');
        $this->addSql('ALTER TABLE attribute_options ADD CONSTRAINT attribute_options_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE attribute_options ADD CONSTRAINT attribute_options_attribute_fk FOREIGN KEY (attribute_id) REFERENCES attributes (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attribute_options DROP CONSTRAINT attribute_options_attribute_fk');
        $this->addSql('ALTER TABLE attribute_options DROP CONSTRAINT attribute_options_tenant_fk');
        $this->addSql('DROP TABLE attribute_options');

        $this->addSql('ALTER TABLE attributes DROP CONSTRAINT attributes_group_fk');
        $this->addSql('ALTER TABLE attributes DROP CONSTRAINT attributes_tenant_fk');
        $this->addSql('DROP TABLE attributes');

        $this->addSql('ALTER TABLE attribute_groups DROP CONSTRAINT attribute_groups_tenant_fk');
        $this->addSql('DROP TABLE attribute_groups');
    }
}
