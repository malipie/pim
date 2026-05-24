<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * ADR-014 / MOD-02 (#894) — `object_relations` replaces `object_associations`.
 *
 * The pre-ADR-014 association infrastructure (`object_associations` linking
 * source/target via `association_types.code`) is dormant — no production
 * consumers (controller/service/UI sweep on 2026-05-24 returned zero
 * references besides the entity, two repos, the seeder, and a single
 * MockBadge tooltip in `product-detail-page.tsx`). The four hardcoded
 * codes (`cross_sell`, `up_sell`, `related`, `accessories`) were an
 * MVP placeholder; ADR-014 generalises them into `relation`-typed
 * Attributes seeded on the Product ObjectType.
 *
 * Per the operator decision in plan-mode (2026-05-24) the data migration
 * step is skipped — there is nothing to back-port, and the dormant tables
 * drop cleanly. The seeder `BuiltInProductRelationAttributesSeeder`
 * handles per-tenant seeding of the 5 attribute rows (`cross_sell`,
 * `up_sell`, `related`, `alternative`, `accessory`).
 *
 * Schema:
 * - Surrogate UUID PK; `(source, target, attribute)` triple is unique.
 * - `tenant_id` for the TenantFilter (idx_tenant + every read carries it
 *   in the WHERE clause).
 * - CHECK forbids self-loops.
 * - `ON DELETE CASCADE` on source/target FKs — removing either object
 *   reaps the link row. `ON DELETE RESTRICT` on attribute_id — deleting
 *   the relation attribute requires removing every link first (UI gates
 *   it via the same Where-used check that already lives on attributes).
 * - `metadata JSONB DEFAULT '{}'` — advanced-relation metadata payload
 *   landed by MOD-08; default empty object keeps non-advanced links a
 *   single column INSERT.
 *
 * Down: recreate `association_types` + `object_associations` schema as
 * shipped by Version20260429050326 (without the seed INSERTs — restoring
 * exact data is out of scope), drop `object_relations`.
 */
final class Version20260524110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ADR-014 / MOD-02 (#894): add object_relations + drop dormant object_associations / association_types.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE object_relations (
              id UUID NOT NULL,
              tenant_id UUID NOT NULL,
              source_object_id UUID NOT NULL,
              target_object_id UUID NOT NULL,
              attribute_id UUID NOT NULL,
              position INT NOT NULL DEFAULT 0,
              metadata JSONB NOT NULL DEFAULT '{}'::JSONB,
              created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL DEFAULT NOW(),
              PRIMARY KEY (id),
              CONSTRAINT object_relations_no_self_loop_chk CHECK (source_object_id <> target_object_id)
            )
        SQL);

        $this->addSql('CREATE INDEX object_relations_tenant_idx ON object_relations (tenant_id)');
        $this->addSql('CREATE INDEX object_relations_source_attribute_idx ON object_relations (source_object_id, attribute_id)');
        $this->addSql('CREATE INDEX object_relations_target_idx ON object_relations (target_object_id)');
        $this->addSql('CREATE UNIQUE INDEX object_relations_triple_uniq ON object_relations (source_object_id, target_object_id, attribute_id)');

        $this->addSql('ALTER TABLE object_relations ADD CONSTRAINT object_relations_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE object_relations ADD CONSTRAINT object_relations_source_fk FOREIGN KEY (source_object_id) REFERENCES objects (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE object_relations ADD CONSTRAINT object_relations_target_fk FOREIGN KEY (target_object_id) REFERENCES objects (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE object_relations ADD CONSTRAINT object_relations_attribute_fk FOREIGN KEY (attribute_id) REFERENCES attributes (id) ON DELETE RESTRICT NOT DEFERRABLE');

        // Drop dormant association infrastructure.
        $this->addSql('ALTER TABLE object_associations DROP CONSTRAINT IF EXISTS object_associations_type_fk');
        $this->addSql('ALTER TABLE object_associations DROP CONSTRAINT IF EXISTS object_associations_target_fk');
        $this->addSql('ALTER TABLE object_associations DROP CONSTRAINT IF EXISTS object_associations_source_fk');
        $this->addSql('ALTER TABLE object_associations DROP CONSTRAINT IF EXISTS object_associations_tenant_fk');
        $this->addSql('DROP TABLE IF EXISTS object_associations');

        $this->addSql('ALTER TABLE association_types DROP CONSTRAINT IF EXISTS association_types_tenant_fk');
        $this->addSql('DROP TABLE IF EXISTS association_types');
    }

    public function down(Schema $schema): void
    {
        // Recreate dormant tables as shipped by Version20260429050326.
        // Pre-existing seed rows (the four default association_types per
        // tenant) are NOT restored — the dormant data is unrecoverable
        // by design.
        $this->addSql(<<<'SQL'
            CREATE TABLE association_types (
              id UUID NOT NULL,
              tenant_id UUID NOT NULL,
              code VARCHAR(64) NOT NULL,
              label JSONB NOT NULL,
              position INT DEFAULT 0 NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX association_types_tenant_idx ON association_types (tenant_id)');
        $this->addSql('CREATE INDEX association_types_tenant_position_idx ON association_types (tenant_id, position)');
        $this->addSql('CREATE UNIQUE INDEX association_types_tenant_code_uniq ON association_types (tenant_id, code)');
        $this->addSql('ALTER TABLE association_types ADD CONSTRAINT association_types_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT NOT DEFERRABLE');

        $this->addSql(<<<'SQL'
            CREATE TABLE object_associations (
              id UUID NOT NULL,
              tenant_id UUID NOT NULL,
              source_object_id UUID NOT NULL,
              target_object_id UUID NOT NULL,
              type_id UUID NOT NULL,
              position INT DEFAULT 0 NOT NULL,
              PRIMARY KEY (id),
              CONSTRAINT object_associations_no_self_loop_chk CHECK (source_object_id <> target_object_id)
            )
        SQL);
        $this->addSql('CREATE INDEX object_associations_tenant_idx ON object_associations (tenant_id)');
        $this->addSql('CREATE INDEX object_associations_source_idx ON object_associations (source_object_id)');
        $this->addSql('CREATE INDEX object_associations_target_idx ON object_associations (target_object_id)');
        $this->addSql('CREATE INDEX object_associations_type_idx ON object_associations (type_id)');
        $this->addSql('CREATE INDEX object_associations_source_type_idx ON object_associations (source_object_id, type_id)');
        $this->addSql('CREATE UNIQUE INDEX object_associations_triple_uniq ON object_associations (source_object_id, target_object_id, type_id)');
        $this->addSql('ALTER TABLE object_associations ADD CONSTRAINT object_associations_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE object_associations ADD CONSTRAINT object_associations_source_fk FOREIGN KEY (source_object_id) REFERENCES objects (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE object_associations ADD CONSTRAINT object_associations_target_fk FOREIGN KEY (target_object_id) REFERENCES objects (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE object_associations ADD CONSTRAINT object_associations_type_fk FOREIGN KEY (type_id) REFERENCES association_types (id) ON DELETE RESTRICT NOT DEFERRABLE');

        $this->addSql('ALTER TABLE object_relations DROP CONSTRAINT IF EXISTS object_relations_attribute_fk');
        $this->addSql('ALTER TABLE object_relations DROP CONSTRAINT IF EXISTS object_relations_target_fk');
        $this->addSql('ALTER TABLE object_relations DROP CONSTRAINT IF EXISTS object_relations_source_fk');
        $this->addSql('ALTER TABLE object_relations DROP CONSTRAINT IF EXISTS object_relations_tenant_fk');
        $this->addSql('DROP TABLE IF EXISTS object_relations');
    }
}
