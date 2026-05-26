<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * UX-02 — purge the system-shipped "Multimedia" AttributeGroup row(s).
 *
 * The previous iteration (ADR-014 / MODR-02 #924) shipped a built-in
 * `code='media'` AttributeGroup so the Multimedia tab could be derived
 * from `effectiveGroups` like any other group. UX-02 reverses that: the
 * operator decided Multimedia is a capability of the ObjectType (a flag
 * driving a hardcoded conditional tab), not an attribute group. Adding
 * attributes to a "Multimedia" group never made sense semantically.
 *
 * Cleanup contract:
 *   - delete rows from `attribute_groups` where `code IN ('media','multimedia')`
 *   - FK ON DELETE CASCADE on `object_type_attribute_groups.attribute_group_id`
 *     + `attribute_group_attributes.attribute_group_id` propagates the
 *     delete to junction rows + any attribute attachments
 *   - user-created groups with other codes (e.g. `hero_images`) are
 *     untouched — only the seeder-managed `media` row goes away
 *   - no-op on fresh installs (zero rows to delete).
 */
final class Version20260526110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'UX-02: drop the built-in Multimedia AttributeGroup (media/multimedia codes); capability moves to object_types.has_multimedia.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DELETE FROM attribute_groups WHERE code IN ('media', 'multimedia')
        SQL);
    }

    public function down(Schema $schema): void
    {
        // Irreversible — the BuiltInProductMediaAttributesSeeder that created
        // this row has been deleted by UX-02 in the same change set. A real
        // rollback would have to restore the seeder first.
        $this->throwIrreversibleMigrationException(
            'UX-02 multimedia AttributeGroup purge cannot be reverted because the seeder was removed in the same change set.',
        );
    }
}
