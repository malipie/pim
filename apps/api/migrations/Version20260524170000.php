<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * ADR-014 / MODR-08 (#930) — adds `relation_preview_fields` to the
 * `attributes` table. The column carries a JSONB list of target
 * attribute codes that should render inside the relation widget's
 * preview card on the product detail page. Empty list = default
 * preview (target object code + name).
 *
 * The column is only meaningful for attributes whose `type='relation'`;
 * other types ignore it.
 */
final class Version20260524170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ADR-014 / MODR-08 (#930): add attributes.relation_preview_fields JSONB.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE attributes
                ADD COLUMN relation_preview_fields JSONB NOT NULL DEFAULT '[]'::jsonb
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE attributes DROP COLUMN relation_preview_fields
        SQL);
    }
}
