<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * IMP2-2.3 (#1479) — crash/pause checkpoint on import_sessions.
 *
 * `checkpoint_offset` is the last data-row index whose chunk was flushed;
 * a resumed (or redelivered) run skips writes at/below it and continues the
 * persisted counters, so pause/resume and worker crashes never duplicate
 * rows. `checkpoint_phase` records which phase (rows | relations) the offset
 * belongs to, and `paused_at` is an audit timestamp for the operator UI.
 */
final class Version20260614170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'IMP2-2.3: import_sessions checkpoint_offset / checkpoint_phase / paused_at';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE import_sessions ADD checkpoint_offset INT DEFAULT NULL');
        $this->addSql('ALTER TABLE import_sessions ADD checkpoint_phase VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE import_sessions ADD paused_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE import_sessions DROP COLUMN checkpoint_offset');
        $this->addSql('ALTER TABLE import_sessions DROP COLUMN checkpoint_phase');
        $this->addSql('ALTER TABLE import_sessions DROP COLUMN paused_at');
    }
}
