<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * MODRC-01 (#1067) — purge the built-in "Powiązania" AttributeGroup and
 * its five seeded `relation` attributes (cross_sell / up_sell / related /
 * alternative / accessory).
 *
 * Option Y (2026-05-26) reverses MOD-02 (#894): a relation is a regular
 * attribute type, not a seeded fixture. Operators create relation
 * attributes and the hosting AttributeGroup explicitly via the modeling
 * wizard (MODRC-02 inline create-group flow). Seeded rows would
 * contradict the discoverability goal — every group must exist because
 * the operator created it.
 *
 * Cleanup order matters because `attributes.id` has two ON DELETE
 * RESTRICT FKs (`object_relations.attribute_id` + `object_type_attributes`)
 * — we wipe the dependent rows first, then the attributes, finally the
 * group. CASCADE handles `attribute_group_attributes` and
 * `object_type_attribute_groups`.
 *
 * Idempotent: every statement is a WHERE-filtered DELETE, so a fresh
 * install with no seeded rows is a no-op.
 */
final class Version20260527090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'MODRC-01: un-seed Powiązania AttributeGroup + 5 built-in relation attributes (Option Y).';
    }

    public function up(Schema $schema): void
    {
        // Step 1 — strip dependent rows on `object_relations` so the
        // ON DELETE RESTRICT FK on attribute_id does not block step 3.
        $this->addSql(<<<'SQL'
            DELETE FROM object_relations
            WHERE attribute_id IN (
                SELECT id FROM attributes
                WHERE code IN ('cross_sell', 'up_sell', 'related', 'alternative', 'accessory')
                  AND is_system = TRUE
            )
        SQL);

        // Step 2 — strip ObjectType ↔ Attribute junctions (ON DELETE
        // RESTRICT on attribute_id).
        $this->addSql(<<<'SQL'
            DELETE FROM object_type_attributes
            WHERE attribute_id IN (
                SELECT id FROM attributes
                WHERE code IN ('cross_sell', 'up_sell', 'related', 'alternative', 'accessory')
                  AND is_system = TRUE
            )
        SQL);

        // Step 3 — delete the seeded relation attributes themselves.
        // CASCADE removes any attribute_group_attributes rows pointing
        // at them.
        $this->addSql(<<<'SQL'
            DELETE FROM attributes
            WHERE code IN ('cross_sell', 'up_sell', 'related', 'alternative', 'accessory')
              AND is_system = TRUE
        SQL);

        // Step 4 — delete the system-shipped "Powiązania" group. CASCADE
        // removes `object_type_attribute_groups` junctions to Product.
        $this->addSql(<<<'SQL'
            DELETE FROM attribute_groups
            WHERE code = 'relations' AND is_system_group = TRUE
        SQL);
    }

    public function down(Schema $schema): void
    {
        // Irreversible — the seeder that created these rows has been
        // deleted by MODRC-01 in the same change set. Restoring would
        // require reintroducing the seeder code first.
        $this->throwIrreversibleMigrationException(
            'MODRC-01 un-seed cannot be reverted because BuiltInProductRelationAttributesSeeder was removed in the same change set.',
        );
    }
}
