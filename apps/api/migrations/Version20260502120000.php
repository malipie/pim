<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * VIEW-01 (#372) — Modelowanie · Object Types pixel-perfect rebuild.
 *
 * Two paired schema additions, neither destructive:
 *
 *   1. `object_types` gains four configurable settings columns
 *      (`hierarchical`, `has_variants`, `abstract`, `allowed_parent_type_ids`)
 *      so admins can edit them through the UI rather than relying on
 *      hard-coded behavior per `kind`. Backfill matches current implicit
 *      defaults: built-in `product` → has_variants, `category` → hierarchical.
 *
 *   2. `tenants` gains `enabled_locales` (JSONB list) and `primary_locale`
 *      (varchar). The CHECK constraint guarantees the primary is always in
 *      the enabled list, so the FE LocaleTabsField cannot end up with a
 *      primary tab pointing at a removed locale. Default `["pl","en"]` /
 *      `pl` matches the seeded demo workspace and the existing fixtures.
 *
 * No expand-contract dance: both tables are < 10k rows in any deployment
 * (CLAUDE.md tolerance for in-place migrations). Indexes on `tenants`
 * are unchanged; the new ObjectType index pair `(tenant_id, kind)` /
 * `(tenant_id, is_built_in)` is the read-path primary lookup for the
 * VIEW-01 list view (built-in vs. custom split).
 */
final class Version20260502120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'VIEW-01 #372 — ObjectType settings columns + Tenant locales (enabled_locales, primary_locale).';
    }

    public function up(Schema $schema): void
    {
        // --- ObjectType: configurable settings exposed in modeling UI ---
        $this->addSql('ALTER TABLE object_types ADD COLUMN hierarchical BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE object_types ADD COLUMN has_variants BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE object_types ADD COLUMN abstract BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql("ALTER TABLE object_types ADD COLUMN allowed_parent_type_ids JSONB NOT NULL DEFAULT '[]'::jsonb");

        // Backfill built-ins so the UI badges (`hierarchical` / `variants`)
        // immediately reflect existing seeded data — without this the badges
        // would silently disappear after the migration runs and before the
        // BuiltInObjectTypeSeeder is re-run on next deploy.
        $this->addSql("UPDATE object_types SET has_variants = TRUE WHERE kind = 'product'");
        $this->addSql("UPDATE object_types SET hierarchical = TRUE WHERE kind = 'category'");

        // The (tenant_id, kind) index already exists from the original
        // schema (object_types_tenant_kind_idx); only built_in is new.
        $this->addSql('CREATE INDEX idx_object_types_built_in ON object_types (tenant_id, is_built_in)');

        // --- Tenant: per-workspace enabled locales ---
        $this->addSql("ALTER TABLE tenants ADD COLUMN enabled_locales JSONB NOT NULL DEFAULT '[\"pl\",\"en\"]'::jsonb");
        $this->addSql("ALTER TABLE tenants ADD COLUMN primary_locale VARCHAR(8) NOT NULL DEFAULT 'pl'");

        // CHECK: primary_locale must appear in enabled_locales. Wrapping in
        // to_jsonb gives a single-value JSON string the @> containment
        // operator can match against.
        $this->addSql('ALTER TABLE tenants ADD CONSTRAINT chk_tenants_primary_locale_in_enabled CHECK (enabled_locales @> to_jsonb(primary_locale))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenants DROP CONSTRAINT IF EXISTS chk_tenants_primary_locale_in_enabled');
        $this->addSql('ALTER TABLE tenants DROP COLUMN IF EXISTS primary_locale');
        $this->addSql('ALTER TABLE tenants DROP COLUMN IF EXISTS enabled_locales');

        $this->addSql('DROP INDEX IF EXISTS idx_object_types_built_in');
        $this->addSql('ALTER TABLE object_types DROP COLUMN IF EXISTS allowed_parent_type_ids');
        $this->addSql('ALTER TABLE object_types DROP COLUMN IF EXISTS abstract');
        $this->addSql('ALTER TABLE object_types DROP COLUMN IF EXISTS has_variants');
        $this->addSql('ALTER TABLE object_types DROP COLUMN IF EXISTS hierarchical');
    }
}
