<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * ADR-014 / MODR-01 (#923) — adds per-assignment `display_mode` to the
 * `object_type_attribute_groups` junction so the same AttributeGroup can be
 * a tab in one ObjectType and a stacked inline section in another.
 *
 * The column governs the form-schema renderer (MODR-03 #925): `tab` renders
 * the group as its own tab, `stacked` renders it inline as a section on
 * the current tab. Default `tab` keeps the existing UX intact.
 *
 * Down path drops the column and the CHECK constraint.
 */
final class Version20260524150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ADR-014 / MODR-01 (#923): add display_mode (tab|stacked) to object_type_attribute_groups.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE object_type_attribute_groups
                ADD COLUMN display_mode VARCHAR(8) NOT NULL DEFAULT 'tab'
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE object_type_attribute_groups
                ADD CONSTRAINT object_type_attribute_groups_display_mode_check
                CHECK (display_mode IN ('tab', 'stacked'))
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE object_type_attribute_groups
                DROP CONSTRAINT IF EXISTS object_type_attribute_groups_display_mode_check
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE object_type_attribute_groups
                DROP COLUMN display_mode
        SQL);
    }
}
