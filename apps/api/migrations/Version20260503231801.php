<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * VIEW-01c (#414) — Menu visibility + ordering on ObjectType.
 *
 * Adds two columns to `object_types`:
 *   - `display_in_menu BOOLEAN NOT NULL DEFAULT FALSE` — toggles whether
 *     the type renders as a primary sidebar entry. Operator-controlled
 *     UX preference, not a domain invariant; built-in rows can flip it
 *     (different from `hierarchical` / `has_variants` / `abstract`,
 *     which are platform contracts).
 *   - `menu_position INT NOT NULL DEFAULT 0` — ascending sort key used
 *     by GET /api/object_types/menu and the sidebar.
 *
 * Backfill: existing built-in `kind=product/category/asset` rows get
 * `display_in_menu=TRUE` with positions 10/20/30 so the default sidebar
 * keeps the legacy hardcoded layout. Custom rows stay hidden by default;
 * the operator opts them in via the Settings toggle.
 *
 * Index `idx_object_types_menu (display_in_menu, menu_position)` keeps
 * GET /api/object_types/menu under p95 < 300ms even on 200+ types per
 * tenant — the typical query is `WHERE display_in_menu = true ORDER BY
 * menu_position ASC`.
 */
final class Version20260503231801 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'VIEW-01c #414 — ObjectType.displayInMenu + menuPosition columns + sidebar visibility backfill.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE object_types ADD COLUMN display_in_menu BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE object_types ADD COLUMN menu_position INT NOT NULL DEFAULT 0');

        $this->addSql("COMMENT ON COLUMN object_types.display_in_menu IS 'Sidebar visibility flag (operator-controlled, not a domain invariant)'");
        $this->addSql("COMMENT ON COLUMN object_types.menu_position IS 'Ascending sort key for the sidebar'");

        // Backfill built-ins so the default sidebar matches the legacy
        // hardcoded layout (Produkty/Kategorie/Multimedia).
        $this->addSql("UPDATE object_types SET display_in_menu = TRUE, menu_position = 10 WHERE kind = 'product' AND is_built_in = TRUE");
        $this->addSql("UPDATE object_types SET display_in_menu = TRUE, menu_position = 20 WHERE kind = 'category' AND is_built_in = TRUE");
        $this->addSql("UPDATE object_types SET display_in_menu = TRUE, menu_position = 30 WHERE kind = 'asset' AND is_built_in = TRUE");
        $this->addSql("UPDATE object_types SET display_in_menu = TRUE, menu_position = 40 WHERE kind = 'brand' AND is_built_in = TRUE");

        $this->addSql('CREATE INDEX idx_object_types_menu ON object_types (display_in_menu, menu_position)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_object_types_menu');
        $this->addSql('ALTER TABLE object_types DROP COLUMN IF EXISTS menu_position');
        $this->addSql('ALTER TABLE object_types DROP COLUMN IF EXISTS display_in_menu');
    }
}
