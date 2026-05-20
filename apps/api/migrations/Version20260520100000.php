<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * RBAC-P5-021 (#711) — tenant lifecycle columns: `status`, `suspended_at`,
 * `deleted_at`.
 *
 * Status enum:
 *   - 'active'    — default; logins + scheduled tasks proceed normally
 *   - 'suspended' — auth refuses (every user blocked); scheduled tasks
 *                   (imports/exports/syncs) refuse to run
 *   - 'deleted'   — soft-deleted; 30-day recovery window before
 *                   `pim:tenants:purge-deleted` hard-deletes the rows
 *
 * `suspended_at` carries the audit trail for the operator decision;
 * `deleted_at` is BOTH the recovery clock + the soft-delete sentinel
 * (resolver treats `deleted_at IS NOT NULL` as deleted regardless of
 * the `status` value, so a partial migration state can't bypass the
 * delete by mutating status alone).
 */
final class Version20260520100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'RBAC-P5-021 — tenant lifecycle columns (status + suspended_at + deleted_at)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE tenants
                ADD COLUMN IF NOT EXISTS status VARCHAR(16) NOT NULL DEFAULT 'active',
                ADD COLUMN IF NOT EXISTS suspended_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
        SQL);
        // ADD CONSTRAINT IF NOT EXISTS lands in PostgreSQL 14+. CI's
        // Postgres 13 image rejects the syntax, so we guard the create
        // through information_schema lookup — safe for all versions
        // and idempotent on re-runs.
        $this->addSql(<<<'SQL'
            DO $$ BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_constraint WHERE conname = 'tenants_status_check'
                ) THEN
                    ALTER TABLE tenants
                        ADD CONSTRAINT tenants_status_check
                            CHECK (status IN ('active', 'suspended', 'deleted'));
                END IF;
            END $$;
        SQL);
        $this->addSql('CREATE INDEX IF NOT EXISTS tenants_status_idx ON tenants (status)');
        $this->addSql('CREATE INDEX IF NOT EXISTS tenants_deleted_at_idx ON tenants (deleted_at) WHERE deleted_at IS NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS tenants_deleted_at_idx');
        $this->addSql('DROP INDEX IF EXISTS tenants_status_idx');
        $this->addSql('ALTER TABLE tenants DROP CONSTRAINT IF EXISTS tenants_status_check');
        $this->addSql('ALTER TABLE tenants DROP COLUMN IF EXISTS deleted_at');
        $this->addSql('ALTER TABLE tenants DROP COLUMN IF EXISTS suspended_at');
        $this->addSql('ALTER TABLE tenants DROP COLUMN IF EXISTS status');
    }
}
