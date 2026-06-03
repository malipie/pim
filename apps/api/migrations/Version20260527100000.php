<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * #1074/#1075 — make audit AttributeGroup optional.
 *
 * The four system attributes remain in `attributes` with `is_system=true`,
 * but the old seeded `code='audit'` AttributeGroup is no longer forced into
 * every ObjectType. Deleting the group cascades old group↔attribute and
 * ObjectType↔group junctions through existing FKs.
 */
final class Version20260527100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make audit AttributeGroup optional by removing legacy seeded audit rows; keep system attributes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DELETE FROM attribute_groups
            WHERE code = 'audit'
              AND is_system_group = true
              AND auto_attached = true
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Audit AttributeGroup is now user-managed modeling configuration; recreate it manually if needed.',
        );
    }
}