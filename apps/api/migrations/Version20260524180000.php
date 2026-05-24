<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * ADR-014 / MODR-10 (#932) — adds optimistic locking to `objects` via a
 * `version` column. Doctrine's `@Version` mapping bumps the value on
 * every UPDATE; `UpdateCatalogObjectHandler` rejects writes whose
 * client-supplied `expectedVersion` is stale, returning HTTP 409.
 *
 * Backfill: every existing row starts at `version = 1`. Concurrent
 * writes already in flight when this migration runs will collide on
 * the first PATCH after; that is acceptable for the dev path.
 */
final class Version20260524180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ADR-014 / MODR-10 (#932): add objects.version for optimistic locking.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE objects
                ADD COLUMN version INTEGER NOT NULL DEFAULT 1
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE objects DROP COLUMN version
        SQL);
    }
}
