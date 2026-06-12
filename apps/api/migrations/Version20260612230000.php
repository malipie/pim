<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * IMP2-1.3 (#1465, ADR-0019 D1/D3/D11) — real import modes.
 *
 * Shrinks ImportMode to CREATE/UPDATE/UPSERT (legacy values were never
 * implemented; hydrating them after the enum change would throw), stores
 * the run configuration on the session (mode + match key) and adds the
 * updated/skipped counters the session view buckets read.
 */
final class Version20260612230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ADR-0019 D3: import modes CREATE/UPDATE/UPSERT — profile data map, session mode/match_attribute_code + updated/skipped counters';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE import_profiles SET mode = 'CREATE' WHERE mode = 'ADD'");
        $this->addSql("UPDATE import_profiles SET mode = 'UPSERT' WHERE mode IN ('MERGE', 'INCREMENT', 'DELETE')");
        $this->addSql("ALTER TABLE import_profiles ALTER COLUMN mode SET DEFAULT 'UPSERT'");
        $this->addSql('ALTER TABLE import_profiles ADD match_attribute_code VARCHAR(64) DEFAULT NULL');

        $this->addSql("ALTER TABLE import_sessions ADD mode VARCHAR(16) NOT NULL DEFAULT 'UPSERT'");
        $this->addSql('ALTER TABLE import_sessions ADD match_attribute_code VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE import_sessions ADD updated_count INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE import_sessions ADD skipped_count INT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE import_sessions DROP COLUMN skipped_count');
        $this->addSql('ALTER TABLE import_sessions DROP COLUMN updated_count');
        $this->addSql('ALTER TABLE import_sessions DROP COLUMN match_attribute_code');
        $this->addSql('ALTER TABLE import_sessions DROP COLUMN mode');
        $this->addSql('ALTER TABLE import_profiles DROP COLUMN match_attribute_code');
        $this->addSql("ALTER TABLE import_profiles ALTER COLUMN mode SET DEFAULT 'UPDATE'");
    }
}
