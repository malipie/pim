<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * ADR-014 / MODR-03 (#925) — preserve the pre-existing visual layout of
 * the Product detail page by shifting the seeded `audit` AttributeGroup
 * junctions from `display_mode='tab'` (DB default introduced in MODR-01
 * #923) to `display_mode='stacked'`. The new dynamic renderer puts every
 * `tab`-mode group in its own tab; without this shift the audit group
 * would jump out of the unified "Attributes" tab and become a sibling
 * of Multimedia / Relations — a regression vs. the prior layout.
 *
 * Down path puts audit junctions back to `tab` (matching the
 * MODR-01 column default).
 */
final class Version20260524160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ADR-014 / MODR-03 (#925): pin audit AttributeGroup junctions to display_mode=stacked.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE object_type_attribute_groups otag
            SET display_mode = 'stacked'
            FROM attribute_groups ag
            WHERE otag.attribute_group_id = ag.id
              AND ag.code = 'audit'
              AND ag.is_system_group = true
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            UPDATE object_type_attribute_groups otag
            SET display_mode = 'tab'
            FROM attribute_groups ag
            WHERE otag.attribute_group_id = ag.id
              AND ag.code = 'audit'
              AND ag.is_system_group = true
        SQL);
    }
}
