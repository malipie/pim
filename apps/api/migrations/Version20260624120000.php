<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * #1718 — add the `create_missing_options` opt-in flag to import_profiles and
 * import_sessions. When true, an import run mints missing select/multiselect
 * AttributeOptions instead of failing rows with unknown values.
 *
 * NOT NULL DEFAULT false: existing rows and any code path that does not set
 * the flag keep the prior strict behaviour.
 */
final class Version20260624120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '#1718: add create_missing_options flag to import_profiles and import_sessions.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE import_profiles ADD create_missing_options BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE import_sessions ADD create_missing_options BOOLEAN DEFAULT false NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE import_profiles DROP create_missing_options');
        $this->addSql('ALTER TABLE import_sessions DROP create_missing_options');
    }
}
