<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * VIEW-02 (#374) — Modelowanie · Attributes Library pixel-perfect rebuild.
 *
 * Adds three columns to `attribute_options` to back the Allowed Values
 * editor (`/modeling/attributes/{code}/values`):
 *
 *   - `color VARCHAR(7) NULL` — optional hex color (#RRGGBB) shown as a
 *     swatch dot in the FE preview and in any future channel exports
 *     (Shopify metafields, Allegro spec). Hex format enforced by CHECK.
 *   - `is_default BOOLEAN NOT NULL DEFAULT false` — at most one default
 *     per attribute, enforced by a partial unique index. Default values
 *     are pre-selected when a new object instance is created.
 *   - `is_deprecated BOOLEAN NOT NULL DEFAULT false` — deprecated values
 *     are hidden from new forms but kept in existing object_values so
 *     historical data is preserved (no destructive migration on rename).
 *
 * The `(attribute_id, position)` index already exists from the original
 * Sprint-0 schema; we only add the partial unique on default.
 *
 * In-place migration: attribute_options has < 1k rows in any deployment
 * (5 currencies, 5 VAT rates, 7 IP ratings, ~50 tags max). All defaults
 * preserve existing rows untouched.
 */
final class Version20260502130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'VIEW-02 #374 — AttributeOption.color/isDefault/isDeprecated for Allowed Values editor.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attribute_options ADD COLUMN color VARCHAR(7) NULL');
        $this->addSql('ALTER TABLE attribute_options ADD COLUMN is_default BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE attribute_options ADD COLUMN is_deprecated BOOLEAN NOT NULL DEFAULT FALSE');

        // Hex format guard — null OR "#RRGGBB". The FE picker enforces this
        // too, but the DB-level CHECK protects against direct SQL writes
        // and migrations from external tools.
        $this->addSql("ALTER TABLE attribute_options ADD CONSTRAINT chk_attribute_options_color_hex CHECK (color IS NULL OR color ~ '^#[0-9A-Fa-f]{6}$')");

        // At most one default per attribute. Partial unique on the
        // boolean=true subset is the canonical Postgres pattern (vs.
        // a full unique with NULLs distinct).
        $this->addSql('CREATE UNIQUE INDEX idx_attribute_options_one_default ON attribute_options (attribute_id) WHERE is_default = TRUE');

        $this->addSql("COMMENT ON COLUMN attribute_options.color IS 'Hex color #RRGGBB for UI swatch display'");
        $this->addSql("COMMENT ON COLUMN attribute_options.is_default IS 'One option per attribute can be default — enforced by partial unique index'");
        $this->addSql("COMMENT ON COLUMN attribute_options.is_deprecated IS 'Deprecated values hidden in new forms but kept in existing object_values'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_attribute_options_one_default');
        $this->addSql('ALTER TABLE attribute_options DROP CONSTRAINT IF EXISTS chk_attribute_options_color_hex');
        $this->addSql('ALTER TABLE attribute_options DROP COLUMN IF EXISTS is_deprecated');
        $this->addSql('ALTER TABLE attribute_options DROP COLUMN IF EXISTS is_default');
        $this->addSql('ALTER TABLE attribute_options DROP COLUMN IF EXISTS color');
    }
}
