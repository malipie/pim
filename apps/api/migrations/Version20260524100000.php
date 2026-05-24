<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * ADR-014 / MOD-01 (#893) — schema delta for capability flags + relation attribute config.
 *
 * Adds:
 *   - `object_types.is_categorizable BOOLEAN` — gates whether instances of
 *     this ObjectType participate in primary-category-driven attribute
 *     distribution. Seeded `true` for `kind=product`, `false` otherwise.
 *     The other capability flag from ADR-014 (`show_in_main_menu`) reuses
 *     the existing `expose_to_main_menu` column (VIEW-08 / #427); no rename.
 *
 *   - `attributes.relation_target_object_type_ids JSONB DEFAULT '[]'` —
 *     list of allowed target ObjectType IDs for attributes of type
 *     `relation`. Empty list = unconstrained (any ObjectType acceptable).
 *
 *   - `attributes.relation_cardinality VARCHAR(8)` with CHECK constraint
 *     limited to `('one', 'many')`. NULL for non-relation attribute types.
 *
 *   - `attributes.relation_advanced BOOLEAN DEFAULT false` — flips on
 *     metadata-bearing relations (own fields on the link itself; UX
 *     materialized in MOD-08).
 *
 * Two items from the MOD-01 spec are intentionally NOT included because
 * they already exist in the schema:
 *   - `object_categories.is_primary BOOLEAN` + partial unique index —
 *     shipped in PCAT-01 (`Version20260510221123`).
 *   - `AttributeType::Relation` enum case — already present in
 *     `AttributeType` since the initial #31 backlog (line 36).
 *
 * Reversible: down() drops the four added columns. `is_categorizable`
 * default propagates to all rows so the rollback is safe even for tenants
 * created post-MOD-01.
 */
final class Version20260524100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ADR-014 / MOD-01 (#893): add object_types.is_categorizable + attributes.relation_* config columns.';
    }

    public function up(Schema $schema): void
    {
        // ObjectType capability flag.
        $this->addSql('ALTER TABLE object_types ADD COLUMN is_categorizable BOOLEAN NOT NULL DEFAULT false');

        // Seed the historical Product-as-categorizable assumption. New tenants
        // get the same default through BuiltInObjectTypeSeeder.
        $this->addSql("UPDATE object_types SET is_categorizable = true WHERE kind = 'product'");

        // Attribute relation-type config columns.
        $this->addSql(<<<'SQL'
            ALTER TABLE attributes
                ADD COLUMN relation_target_object_type_ids JSONB NOT NULL DEFAULT '[]'::JSONB,
                ADD COLUMN relation_cardinality VARCHAR(8) DEFAULT NULL,
                ADD COLUMN relation_advanced BOOLEAN NOT NULL DEFAULT false
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE attributes
                ADD CONSTRAINT attributes_relation_cardinality_chk
                CHECK (relation_cardinality IS NULL OR relation_cardinality IN ('one', 'many'))
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attributes DROP CONSTRAINT IF EXISTS attributes_relation_cardinality_chk');
        $this->addSql('ALTER TABLE attributes DROP COLUMN IF EXISTS relation_advanced');
        $this->addSql('ALTER TABLE attributes DROP COLUMN IF EXISTS relation_cardinality');
        $this->addSql('ALTER TABLE attributes DROP COLUMN IF EXISTS relation_target_object_type_ids');
        $this->addSql('ALTER TABLE object_types DROP COLUMN IF EXISTS is_categorizable');
    }
}
