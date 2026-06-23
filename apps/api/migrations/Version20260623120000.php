<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Amends ADR-009 — make `Asset` / `Category` closed system ObjectTypes.
 *
 * These kinds were briefly attribute-modelable: the demo seeder attached
 * `name`, `alt_text`, `caption` to asset and `name`, `seo_title`,
 * `seo_description`, `main_image` to category. The product direction is that
 * asset/category are platform-managed — their intrinsic fields (name as
 * display label, asset code/tags/file metadata, category path) are owned by
 * dedicated controllers/UI, not the generic attribute model. The modeling
 * detail page already redirects these kinds away, leaving the attachments
 * un-detachable through the UI (the dead-end that surfaced this).
 *
 * This migration cleans existing data so the live model matches the fixed
 * seeder + the new API guard:
 *   1. drop ObjectValues for the four removed attribute codes;
 *   2. drop ALL `object_type_attributes` rows for kind asset/category;
 *   3. delete the now-orphaned attribute definitions (guarded — only when
 *      nothing references them); `name` / `main_image` stay (product uses
 *      them);
 *   4. scrub the stale `seo_title` reference from category completeness rules.
 *
 * Production tenants never ran the demo seeder, so they have no asset/category
 * attachments — the guarded statements are no-ops there.
 *
 * Down: irreversible. This removes demo-only attribute attachments and
 * definitions; recreating them would only reintroduce the leftover state this
 * migration set out to remove.
 */
final class Version20260623120000 extends AbstractMigration
{
    /**
     * @var list<string>
     */
    private const array REMOVED_CODES = ['alt_text', 'caption', 'seo_title', 'seo_description'];

    public function getDescription(): string
    {
        return 'ADR-009 amend: Asset/Category become closed system kinds — detach all attributes, drop orphaned alt_text/caption/seo_title/seo_description.';
    }

    public function up(Schema $schema): void
    {
        $codes = "'".implode("','", self::REMOVED_CODES)."'";

        // 1. Drop values for the removed codes first (object_values FK to
        //    attributes is ON DELETE RESTRICT, so they must go before step 3).
        $this->addSql(
            'DELETE FROM object_values ov USING attributes a'
            .' WHERE ov.attribute_id = a.id AND a.code IN ('.$codes.')',
        );

        // 2. Detach every attribute from closed system kinds — zero junctions
        //    for asset/category (this also removes the name/main_image rows for
        //    those kinds; the attribute definitions themselves are untouched).
        $this->addSql(
            "DELETE FROM object_type_attributes ota USING object_types ot"
            ." WHERE ota.object_type_id = ot.id AND ot.kind IN ('asset','category')",
        );

        // 3. Delete the now-orphaned attribute definitions — guarded so we
        //    never drop one still referenced by a junction or a value.
        $this->addSql(
            'DELETE FROM attributes a WHERE a.code IN ('.$codes.')'
            .' AND NOT EXISTS (SELECT 1 FROM object_type_attributes ota WHERE ota.attribute_id = a.id)'
            .' AND NOT EXISTS (SELECT 1 FROM object_values ov WHERE ov.attribute_id = a.id)',
        );

        // 4. Category completeness rules referenced the deleted seo_title —
        //    collapse to name-only so the dangling code does not linger.
        $this->addSql(
            "UPDATE object_types SET completeness_rules = '{\"required\": [\"name\"]}'::jsonb"
            ." WHERE kind = 'category' AND completeness_rules::text LIKE '%seo_title%'",
        );
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Asset/Category attribute attachments and the alt_text/caption/seo_title/seo_description definitions were demo-only leftovers; recreating them would reintroduce the state ADR-009 amend removed.',
        );
    }
}
