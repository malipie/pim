<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * IMP-04 (#445) — captures the header → attribute_code mapping on the
 * session itself. Profile-less ad-hoc imports keep their mapping after
 * the request returns; the optional `import_profiles.column_mapping`
 * is now a "save for next time" snapshot rather than the source of
 * truth for an in-flight session.
 */
final class Version20260506213907 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'IMP-04: import_sessions.column_mapping for profile-less imports.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE import_sessions ADD COLUMN column_mapping JSONB NOT NULL DEFAULT '{}'::jsonb");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE import_sessions DROP COLUMN IF EXISTS column_mapping');
    }
}
