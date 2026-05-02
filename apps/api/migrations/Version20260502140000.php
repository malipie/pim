<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * VIEW-03 (#375) — Modelowanie · Attribute Groups pixel-perfect rebuild.
 *
 * Adds three boolean columns to `attribute_groups` to back the
 * Behavior toggles in the create form (NewAttributeGroupView mockup,
 * `groups-categories.jsx:570–576`):
 *
 *   - `is_required_section BOOLEAN NOT NULL DEFAULT FALSE` — group is
 *     always rendered in product forms (cannot be skipped/collapsed).
 *   - `is_shared BOOLEAN NOT NULL DEFAULT TRUE` — group can be attached
 *     to multiple ObjectTypes (default), vs. exclusive to one.
 *   - `has_conditional_visibility BOOLEAN NOT NULL DEFAULT FALSE` —
 *     group rendering is gated by `visible_when` rules per attribute
 *     in the junction table.
 *
 * In-place migration: attribute_groups has < 100 rows in any deployment
 * (12 in current demo seed + tenant-specific extensions). Defaults
 * preserve existing rows untouched (`is_shared=TRUE` matches current
 * implicit behavior).
 */
final class Version20260502140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'VIEW-03 #375 — AttributeGroup behavior toggles (is_required_section, is_shared, has_conditional_visibility).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attribute_groups ADD COLUMN is_required_section BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE attribute_groups ADD COLUMN is_shared BOOLEAN NOT NULL DEFAULT TRUE');
        $this->addSql('ALTER TABLE attribute_groups ADD COLUMN has_conditional_visibility BOOLEAN NOT NULL DEFAULT FALSE');

        $this->addSql("COMMENT ON COLUMN attribute_groups.is_required_section IS 'Group always rendered in form (cannot be skipped/collapsed)'");
        $this->addSql("COMMENT ON COLUMN attribute_groups.is_shared IS 'Group can be attached to multiple ObjectTypes (vs. exclusive to one)'");
        $this->addSql("COMMENT ON COLUMN attribute_groups.has_conditional_visibility IS 'Group rendering controlled by visible_when rules per attribute'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE attribute_groups DROP COLUMN IF EXISTS has_conditional_visibility');
        $this->addSql('ALTER TABLE attribute_groups DROP COLUMN IF EXISTS is_shared');
        $this->addSql('ALTER TABLE attribute_groups DROP COLUMN IF EXISTS is_required_section');
    }
}
