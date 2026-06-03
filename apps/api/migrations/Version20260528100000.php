<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * MODRC-01 (#1080) — make relations AttributeGroup optional.
 *
 * Analogous to Version20260527100000 for audit (#1074). The five built-in
 * relation attributes (`cross_sell`, `up_sell`, `related`, `alternative`,
 * `accessory`) remain in `attributes` with `is_system=true`, but the old
 * seeded `code='relations'` AttributeGroup is no longer forced — operators
 * group these attributes themselves via custom AttributeGroups.
 *
 * Deleting the group cascades old group↔attribute and ObjectType↔group
 * junctions through existing FKs.
 */
final class Version20260528100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make relations AttributeGroup optional by removing the legacy seeded "relations" rows; keep relation attributes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DELETE FROM attribute_groups
            WHERE code = 'relations'
              AND is_system_group = true
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Relations AttributeGroup is now user-managed modeling configuration; recreate it manually if needed.',
        );
    }
}
