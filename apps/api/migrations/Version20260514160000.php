<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * VIEW-28 (#559) — `objects.bulk_session_id` denormalized column (PRD §5.2).
 *
 * Last bulk session that touched the row. Cache speeds up the "show me
 * everything bulk_a8f3c2 changed" queries from the VIEW-17 audit panel
 * and the future R-36 benchmark. `ON DELETE SET NULL` (not CASCADE)
 * because deleting an old bulk session must NOT cascade into product
 * rows.
 *
 * Partial index `WHERE bulk_session_id IS NOT NULL` keeps the index
 * small — most rows in steady state have NULL (never touched by bulk).
 */
final class Version20260514160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'VIEW-28 objects.bulk_session_id denormalized cache column (#559).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE objects ADD COLUMN bulk_session_id UUID NULL
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE objects
              ADD CONSTRAINT fk_objects_bulk_session
              FOREIGN KEY (bulk_session_id) REFERENCES bulk_sessions(id)
              ON DELETE SET NULL
            SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_objects_bulk_session
              ON objects (bulk_session_id)
              WHERE bulk_session_id IS NOT NULL
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS idx_objects_bulk_session');
        $this->addSql('ALTER TABLE objects DROP CONSTRAINT IF EXISTS fk_objects_bulk_session');
        $this->addSql('ALTER TABLE objects DROP COLUMN IF EXISTS bulk_session_id');
    }
}
