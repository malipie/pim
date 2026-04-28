<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Epic 0.3 / ticket #34 — Object + ObjectValue tables (post ADR-009).
 *
 * Generic catalog row + the canonical attribute value store. Per ADR-006
 * (hybrid model) every Object carries a denormalised `attributes_indexed
 * JSONB` cache fed by Doctrine listeners (#38) — the GIN index here is
 * the read path that lets `WHERE attributes_indexed @> '{"color":"red"}'`
 * stay sub-50ms on 10k×200×3 datasets (DoD benchmark from #34).
 *
 * Notes vs. the architecture DDL in `Project Plan/01-architektura-pim.md`
 * §5.2:
 *   - `path` is `VARCHAR(4096)` here, not `LTREE`. Doctrine 3 lacks an
 *     ltree DBAL type; we keep the column as text in this migration so
 *     the schema is portable + Doctrine round-trips cleanly. Ticket #33
 *     activates the `ltree` Postgres extension, ALTERs `path` to LTREE,
 *     adds the partial GIST/BTree indexes for `kind = 'category'`, and
 *     wires the validator listener that enforces the kind-↔-path invariant.
 *   - Generated columns (`name_pl`, `sku_for_product`) are deferred to
 *     #38 so we land them together with the listener that populates
 *     `attributes_indexed`. Building generated columns first would be
 *     an empty contract until the cache exists.
 *   - `path` CHECK constraint (`kind = 'category' OR path IS NULL`) is
 *     also #33 — paired with the validator listener.
 *
 * UNIQUE on `object_values (object_id, attribute_id, channel_id, locale)
 * NULLS NOT DISTINCT` — Postgres 15+ syntax. Lets a single global
 * (channel_id NULL, locale NULL) row coexist alongside per-channel
 * variants without juggling COALESCE in PHP. The schema is locked to
 * Postgres 16 (CLAUDE.md "Stack" section), so this is safe.
 *
 * RLS policies on the new tables come in phase 2 along with the rest of
 * the catalog (#30 only covered products + refresh_tokens). Doctrine
 * `TenantFilter` via `TenantScoped` is the only application-layer
 * isolation in MVP.
 *
 * Legacy `products` table left untouched on purpose — data migration
 * `products → objects` + DROP `products` lands in #33 alongside the
 * predefined ObjectType fixtures (each migrated row needs an
 * `object_type_id` FK target).
 */
final class Version20260428220053 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add objects + object_values tables with GIN(attributes_indexed) (post ADR-009) (#34).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE objects (
              id UUID NOT NULL,
              tenant_id UUID NOT NULL,
              object_type_id UUID NOT NULL,
              parent_id UUID DEFAULT NULL,
              kind VARCHAR(32) NOT NULL,
              code VARCHAR(128) NOT NULL,
              enabled BOOLEAN DEFAULT true NOT NULL,
              status VARCHAR(16) DEFAULT 'draft' NOT NULL,
              completeness JSONB DEFAULT '{}' NOT NULL,
              attributes_indexed JSONB DEFAULT '{}' NOT NULL,
              path VARCHAR(4096) DEFAULT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX objects_tenant_idx ON objects (tenant_id)');
        $this->addSql('CREATE INDEX objects_object_type_idx ON objects (object_type_id)');
        $this->addSql('CREATE INDEX objects_parent_idx ON objects (parent_id)');
        $this->addSql('CREATE INDEX objects_tenant_type_idx ON objects (tenant_id, object_type_id)');
        $this->addSql('CREATE INDEX objects_tenant_kind_idx ON objects (tenant_id, kind)');
        $this->addSql('CREATE UNIQUE INDEX objects_tenant_kind_code_uniq ON objects (tenant_id, kind, code)');
        $this->addSql('CREATE INDEX objects_attributes_indexed_gin ON objects USING GIN (attributes_indexed)');
        $this->addSql('ALTER TABLE objects ADD CONSTRAINT objects_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE objects ADD CONSTRAINT objects_object_type_fk FOREIGN KEY (object_type_id) REFERENCES object_types (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE objects ADD CONSTRAINT objects_parent_fk FOREIGN KEY (parent_id) REFERENCES objects (id) ON DELETE SET NULL NOT DEFERRABLE');

        $this->addSql(<<<'SQL'
            CREATE TABLE object_values (
              id UUID NOT NULL,
              tenant_id UUID NOT NULL,
              object_id UUID NOT NULL,
              attribute_id UUID NOT NULL,
              channel_id UUID DEFAULT NULL,
              locale VARCHAR(8) DEFAULT NULL,
              value JSONB NOT NULL,
              provenance VARCHAR(16) DEFAULT 'manual' NOT NULL,
              provenance_meta JSONB DEFAULT '{}' NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX object_values_tenant_idx ON object_values (tenant_id)');
        $this->addSql('CREATE INDEX object_values_object_idx ON object_values (object_id)');
        $this->addSql('CREATE INDEX object_values_attribute_idx ON object_values (attribute_id)');
        // NULLS NOT DISTINCT (Postgres 15+) — global rows (channel_id NULL,
        // locale NULL) collide with each other on (object_id, attribute_id)
        // but not with per-channel variants. Removes COALESCE juggling.
        $this->addSql('CREATE UNIQUE INDEX object_values_scope_uniq ON object_values (object_id, attribute_id, channel_id, locale) NULLS NOT DISTINCT');
        $this->addSql('ALTER TABLE object_values ADD CONSTRAINT object_values_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE object_values ADD CONSTRAINT object_values_object_fk FOREIGN KEY (object_id) REFERENCES objects (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE object_values ADD CONSTRAINT object_values_attribute_fk FOREIGN KEY (attribute_id) REFERENCES attributes (id) ON DELETE RESTRICT NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE object_values DROP CONSTRAINT object_values_attribute_fk');
        $this->addSql('ALTER TABLE object_values DROP CONSTRAINT object_values_object_fk');
        $this->addSql('ALTER TABLE object_values DROP CONSTRAINT object_values_tenant_fk');
        $this->addSql('DROP TABLE object_values');

        $this->addSql('ALTER TABLE objects DROP CONSTRAINT objects_parent_fk');
        $this->addSql('ALTER TABLE objects DROP CONSTRAINT objects_object_type_fk');
        $this->addSql('ALTER TABLE objects DROP CONSTRAINT objects_tenant_fk');
        $this->addSql('DROP TABLE objects');
    }
}
