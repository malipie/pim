<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * IMP2-2.7 (#1483) — import guardrails: per-tenant limits + Allowed-Errors threshold.
 *
 * Three nullable columns (null = application default / OFF), so the migration is
 * additive and needs no backfill (memory feedback_orm_notnull_needs_default does
 * not apply — these are nullable).
 */
final class Version20260615145641 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'IMP2-2.7: tenants.import_max_rows/import_max_file_size + import_profiles.allowed_errors_pct';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenants ADD import_max_rows INT DEFAULT NULL');
        $this->addSql('ALTER TABLE tenants ADD import_max_file_size INT DEFAULT NULL');
        $this->addSql('ALTER TABLE import_profiles ADD allowed_errors_pct INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tenants DROP import_max_rows');
        $this->addSql('ALTER TABLE tenants DROP import_max_file_size');
        $this->addSql('ALTER TABLE import_profiles DROP allowed_errors_pct');
    }
}
