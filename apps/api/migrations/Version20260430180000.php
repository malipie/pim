<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Epic 0.11 / ticket #0.11.1 — TOTP 2FA columns on users.
 *
 * Three nullable columns hold the per-user TOTP state. `totp_enabled_at`
 * doubles as the "is 2FA active?" flag (null until the user confirms
 * their first generated code). Recovery codes are stored as Argon2id
 * hashes in a JSONB array — one-shot, removed on use.
 */
final class Version20260430180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add TOTP 2FA columns (totp_secret, totp_enabled_at, totp_backup_codes) on users.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE users
              ADD COLUMN totp_secret VARCHAR(128) DEFAULT NULL,
              ADD COLUMN totp_enabled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
              ADD COLUMN totp_backup_codes JSONB NOT NULL DEFAULT '[]'::jsonb
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE users
              DROP COLUMN totp_secret,
              DROP COLUMN totp_enabled_at,
              DROP COLUMN totp_backup_codes
            SQL);
    }
}
