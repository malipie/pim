<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * IMP2-1.13 (#1476) — media-from-ZIP source flag on the import session.
 *
 * `image_source` records how Asset-attribute cells resolve for the run:
 * `http` (download URLs — IMP2-1.12), `zip` (extract from the uploaded
 * archive), or `none`. Defaults to `none` so pre-existing sessions are
 * unaffected.
 */
final class Version20260614150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'IMP2-1.13: import_sessions.image_source (http|zip|none)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE import_sessions ADD image_source VARCHAR(8) NOT NULL DEFAULT 'none'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE import_sessions DROP COLUMN image_source');
    }
}
