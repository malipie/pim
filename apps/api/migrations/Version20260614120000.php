<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * IMP2-1.12 (#1475) — media-from-URL completion gating.
 *
 * Adds the two counters the import run needs to defer finalization until
 * every dispatched image-download batch has finished: `pending_image_batches`
 * (decremented by each media handler) and `row_phase_complete` (set once the
 * row-write phase dispatched its last batch). The session only transitions to
 * its terminal state when the row phase is done AND no batch is pending.
 */
final class Version20260614120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'IMP2-1.12: import_sessions media completion gating (pending_image_batches, row_phase_complete)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE import_sessions ADD pending_image_batches INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE import_sessions ADD row_phase_complete BOOLEAN NOT NULL DEFAULT false');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE import_sessions DROP COLUMN pending_image_batches');
        $this->addSql('ALTER TABLE import_sessions DROP COLUMN row_phase_complete');
    }
}
