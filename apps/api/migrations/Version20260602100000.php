<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * #1207 — blameable actor snapshot on `objects`.
 *
 * Adds nullable `created_by` / `updated_by` columns holding the e-mail of the
 * actor who created / last updated the row, stamped by
 * BlameableAssignmentListener on flush. A string snapshot (not a FK to users)
 * is deliberate: "who created this" is a historical audit fact that must
 * survive user rename/deletion, and it keeps the Catalog context free of a
 * hard dependency on Identity. Existing rows stay NULL (no backfill) — they
 * render "—" until next edit.
 */
final class Version20260602100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '#1207: add blameable created_by/updated_by (actor e-mail snapshot) to objects';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE objects ADD COLUMN created_by VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE objects ADD COLUMN updated_by VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE objects DROP COLUMN updated_by');
        $this->addSql('ALTER TABLE objects DROP COLUMN created_by');
    }
}
