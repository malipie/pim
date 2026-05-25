<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * UP-00 (#1017) — add `object_types.has_multimedia` capability flag.
 *
 * Gates the Multimedia tab on the UniversalDetailPage (UP-07) for any
 * ObjectType. Built-in product seeded TRUE to match the legacy hard-coded
 * behaviour (`apps/admin/src/features/catalog/products/show.tsx` always
 * rendered a multimedia tab for products). Other built-ins + custom kinds
 * default FALSE — operator opts in via the ObjectType Capability flags
 * card (UP-07b wizard toggle).
 *
 * Backfill: existing product rows updated to TRUE in the same migration
 * so already-seeded tenants see no regression.
 */
final class Version20260525210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'UP-00 (#1017): add object_types.has_multimedia capability flag, backfill product=true.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE object_types
                ADD COLUMN has_multimedia BOOLEAN NOT NULL DEFAULT FALSE
        SQL);

        // Backfill existing built-in product rows so already-seeded tenants
        // keep their multimedia tab without re-running the seeder.
        $this->addSql(<<<'SQL'
            UPDATE object_types SET has_multimedia = TRUE
            WHERE kind = 'product' AND is_built_in = TRUE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE object_types DROP COLUMN has_multimedia
        SQL);
    }
}
