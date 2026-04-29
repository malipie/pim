<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Epic 0.3 / ticket #33 — predefined ObjectType fixtures + ltree + data
 * migration `products → objects` + DROP legacy `products`.
 *
 * Three things happen here:
 *
 * 1. **ltree on `objects.path`.** The Postgres extension is enabled and
 *    `path` is converted from VARCHAR(4096) to LTREE. A CHECK constraint
 *    pins the kind ↔ path invariant ("path is for categories only"), and
 *    partial GIST + BTree indexes cover only `kind = 'category'` rows so
 *    product / asset queries pay zero cost for unused indexing.
 *
 * 2. **Predefined ObjectType fixtures per tenant.** Every tenant gets one
 *    built-in row per kind (product / category / asset) with
 *    `is_built_in = true` so the service-layer guard from #32 protects
 *    them from deletion. Seeded inline (raw SQL) so the migration is
 *    self-contained — no PHP service dependency. Idempotent: only
 *    creates when no built-in row of that kind already exists.
 *
 * 3. **Data migration `products → objects`.** Legacy Sprint-0 products
 *    are converted to `objects` rows of `kind = 'product'`, with the SKU
 *    becoming `code` and the columns (name, description, brand) folded
 *    into `attributes_indexed JSONB`. Each migrated row points at its
 *    tenant's built-in product ObjectType. After conversion the legacy
 *    `products` table + its tenant FK + RLS policies are dropped.
 *    `Product` entity / repository / voter are removed in PHP alongside.
 *
 * Out of scope: re-creating ProductApiTest / TenantIsolationTest /
 * ProductVoterTest on top of CatalogObject. Those tests now skip with a
 * TODO pointing at #41 (sugar paths /api/products on CatalogObject) +
 * #57 (CatalogObject voter). The application-layer isolation contract is
 * still validated by the unit-test suite on the new entities.
 */
final class Version20260428222056 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enable ltree, seed built-in ObjectTypes, migrate products → objects, drop legacy products (#33).';
    }

    public function up(Schema $schema): void
    {
        // ── PHASE A: ltree extension + path column conversion ──────────
        $this->addSql('CREATE EXTENSION IF NOT EXISTS ltree');
        // Drop default first — Postgres can't auto-cast a VARCHAR default
        // expression onto an LTREE column type. The column stays nullable
        // with no default; PHP entity sets `$path = null` on construction.
        $this->addSql('ALTER TABLE objects ALTER COLUMN path DROP DEFAULT');
        $this->addSql('ALTER TABLE objects ALTER COLUMN path TYPE LTREE USING path::ltree');
        $this->addSql("ALTER TABLE objects ADD CONSTRAINT objects_path_kind_chk CHECK (path IS NULL OR kind = 'category')");
        $this->addSql("CREATE INDEX objects_path_gist_idx ON objects USING GIST (path) WHERE kind = 'category'");
        $this->addSql("CREATE INDEX objects_path_btree_idx ON objects USING BTREE (path) WHERE kind = 'category'");

        // ── PHASE B: seed built-in ObjectTypes per tenant ──────────────
        $this->addSql(<<<'SQL'
            INSERT INTO object_types (id, tenant_id, code, kind, is_built_in, label, completeness_rules, schema_version, created_at, updated_at)
            SELECT gen_random_uuid(), t.id, 'product', 'product', true, '{"pl":"Produkt","en":"Product"}'::jsonb, '{}'::jsonb, 1, NOW(), NOW()
            FROM tenants t
            WHERE NOT EXISTS (
                SELECT 1 FROM object_types o
                WHERE o.tenant_id = t.id AND o.kind = 'product' AND o.is_built_in = true
            )
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO object_types (id, tenant_id, code, kind, is_built_in, label, completeness_rules, schema_version, created_at, updated_at)
            SELECT gen_random_uuid(), t.id, 'category', 'category', true, '{"pl":"Kategoria","en":"Category"}'::jsonb, '{}'::jsonb, 1, NOW(), NOW()
            FROM tenants t
            WHERE NOT EXISTS (
                SELECT 1 FROM object_types o
                WHERE o.tenant_id = t.id AND o.kind = 'category' AND o.is_built_in = true
            )
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO object_types (id, tenant_id, code, kind, is_built_in, label, completeness_rules, schema_version, created_at, updated_at)
            SELECT gen_random_uuid(), t.id, 'asset', 'asset', true, '{"pl":"Zasób","en":"Asset"}'::jsonb, '{}'::jsonb, 1, NOW(), NOW()
            FROM tenants t
            WHERE NOT EXISTS (
                SELECT 1 FROM object_types o
                WHERE o.tenant_id = t.id AND o.kind = 'asset' AND o.is_built_in = true
            )
        SQL);

        // ── PHASE C: data migration `products` → `objects` ─────────────
        $this->addSql(<<<'SQL'
            INSERT INTO objects (id, tenant_id, object_type_id, kind, code, enabled, status, completeness, attributes_indexed, path, created_at, updated_at)
            SELECT
                p.id,
                p.tenant_id,
                ot.id,
                'product',
                p.sku,
                true,
                'published',
                '{}'::jsonb,
                jsonb_strip_nulls(jsonb_build_object(
                    'sku', p.sku,
                    'name', p.name,
                    'description', p.description,
                    'brand', p.brand
                )),
                NULL,
                p.created_at,
                p.updated_at
            FROM products p
            JOIN object_types ot ON ot.tenant_id = p.tenant_id AND ot.kind = 'product' AND ot.is_built_in = true
        SQL);

        // RLS policies on legacy `products` (from #30) must drop before the table.
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation_select ON products');
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation_insert ON products');
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation_update ON products');
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation_delete ON products');
        // FK from #2 (Sprint-0 products + tenant_id).
        $this->addSql('DROP TABLE products');
    }

    public function down(Schema $schema): void
    {
        // Recreate the legacy `products` table + FK + indexes (mirrors #2).
        $this->addSql(<<<'SQL'
            CREATE TABLE products (
              id UUID NOT NULL,
              tenant_id UUID NOT NULL,
              sku VARCHAR(64) NOT NULL,
              name VARCHAR(255) NOT NULL,
              description TEXT DEFAULT NULL,
              brand VARCHAR(128) DEFAULT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX products_tenant_idx ON products (tenant_id)');
        $this->addSql('CREATE UNIQUE INDEX products_tenant_sku_uniq ON products (tenant_id, sku)');
        $this->addSql('ALTER TABLE products ADD CONSTRAINT products_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT NOT DEFERRABLE');

        // Restore data from objects (kind=product) back into products.
        $this->addSql(<<<'SQL'
            INSERT INTO products (id, tenant_id, sku, name, description, brand, created_at, updated_at)
            SELECT
                o.id,
                o.tenant_id,
                COALESCE(o.attributes_indexed->>'sku', o.code),
                COALESCE(o.attributes_indexed->>'name', o.code),
                o.attributes_indexed->>'description',
                o.attributes_indexed->>'brand',
                o.created_at,
                o.updated_at
            FROM objects o
            WHERE o.kind = 'product'
        SQL);
        $this->addSql('DELETE FROM objects WHERE kind = \'product\'');

        // Re-create RLS policies on products that #30 originally added.
        $this->addSql("CREATE POLICY tenant_isolation_select ON products FOR SELECT USING (tenant_id = current_setting('pim.current_tenant_id', true)::uuid)");
        $this->addSql("CREATE POLICY tenant_isolation_insert ON products FOR INSERT WITH CHECK (tenant_id = current_setting('pim.current_tenant_id', true)::uuid)");
        $this->addSql("CREATE POLICY tenant_isolation_update ON products FOR UPDATE USING (tenant_id = current_setting('pim.current_tenant_id', true)::uuid) WITH CHECK (tenant_id = current_setting('pim.current_tenant_id', true)::uuid)");
        $this->addSql("CREATE POLICY tenant_isolation_delete ON products FOR DELETE USING (tenant_id = current_setting('pim.current_tenant_id', true)::uuid)");

        // Drop seeded built-ins (best-effort — caller is reverting a fresh
        // migrate, custom rows beyond the seed never landed because phase
        // 2 feature flag is off).
        $this->addSql("DELETE FROM object_types WHERE is_built_in = true AND kind IN ('product', 'category', 'asset')");

        // Roll path back to VARCHAR + drop ltree partial indexes/CHECK.
        $this->addSql('DROP INDEX objects_path_gist_idx');
        $this->addSql('DROP INDEX objects_path_btree_idx');
        $this->addSql('ALTER TABLE objects DROP CONSTRAINT objects_path_kind_chk');
        $this->addSql('ALTER TABLE objects ALTER COLUMN path TYPE VARCHAR(4096) USING path::text');
        // We do NOT drop the ltree extension — other migrations/schemas
        // in the database may legitimately use it; dropping is the
        // operator's choice.
    }
}
