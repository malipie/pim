<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * ADR-015 — category trees scoped per ObjectType.
 *
 * Until now there was ONE shared category tree per tenant: all `kind='category'`
 * objects lived in a single ltree namespace and the `category_attribute_groups`
 * junction's `target_object_type_id` decided which ObjectType inherited groups.
 *
 * ADR-015 partitions the tree by the ObjectType it organizes. A new
 * `objects.category_target_object_type_id` column marks which categorizable
 * ObjectType's tree a category belongs to (NULL for non-category rows). The
 * per-tenant `(kind, code)` uniqueness is replaced by:
 *   - `(tenant_id, kind, code)` for non-category rows (unchanged semantics),
 *   - `(tenant_id, category_target_object_type_id, code)` for categories, so the
 *     same code (e.g. "elektronika") can exist in two different trees.
 *
 * Backfill (operator decision): existing categories move into the built-in
 * Product tree per tenant — Product is the only `is_categorizable` built-in
 * today and the legacy single tree served it. Other ObjectTypes start with no
 * tree (empty) until the operator flips `is_categorizable` and creates one.
 *
 * Expand-contract: this is the expand step (additive column + index swap). No
 * data is dropped; `down()` restores the original single unique index.
 */
final class Version20260530120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ADR-015: add objects.category_target_object_type_id + per-tree category code uniqueness';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE objects ADD COLUMN category_target_object_type_id UUID DEFAULT NULL');
        $this->addSql(
            'ALTER TABLE objects ADD CONSTRAINT objects_category_target_ot_fk'
            .' FOREIGN KEY (category_target_object_type_id) REFERENCES object_types (id)'
            .' ON DELETE RESTRICT NOT DEFERRABLE'
        );
        $this->addSql('CREATE INDEX objects_category_target_ot_idx ON objects (category_target_object_type_id)');

        // Backfill: existing categories -> built-in Product OT of the same tenant.
        $this->addSql(
            'UPDATE objects o SET category_target_object_type_id = ('
            .' SELECT ot.id FROM object_types ot'
            ." WHERE ot.tenant_id = o.tenant_id AND ot.kind = 'product' AND ot.is_built_in = true"
            .' LIMIT 1'
            .") WHERE o.kind = 'category'"
        );

        // Swap the single per-tenant code uniqueness for kind-aware partial indexes.
        $this->addSql('DROP INDEX objects_tenant_kind_code_uniq');
        $this->addSql(
            'CREATE UNIQUE INDEX objects_tenant_kind_code_noncat_uniq'
            ." ON objects (tenant_id, kind, code) WHERE kind <> 'category'"
        );
        $this->addSql(
            'CREATE UNIQUE INDEX objects_tenant_cat_tree_code_uniq'
            ." ON objects (tenant_id, category_target_object_type_id, code) WHERE kind = 'category'"
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX objects_tenant_cat_tree_code_uniq');
        $this->addSql('DROP INDEX objects_tenant_kind_code_noncat_uniq');
        $this->addSql('CREATE UNIQUE INDEX objects_tenant_kind_code_uniq ON objects (tenant_id, kind, code)');

        $this->addSql('ALTER TABLE objects DROP CONSTRAINT objects_category_target_ot_fk');
        $this->addSql('DROP INDEX objects_category_target_ot_idx');
        $this->addSql('ALTER TABLE objects DROP COLUMN category_target_object_type_id');
    }
}
