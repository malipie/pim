<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Manual user creation (#867) — adds `users.password_change_required`
 * boolean. The flag is set to TRUE when an admin creates a user via
 * `POST /api/users` with the "Wymagaj zmiany przy 1. logowaniu"
 * checkbox; the `/api/me/change-password` endpoint clears it on the
 * first successful change. AuthedRoute on the frontend redirects to
 * `/first-login-password` while the flag is set, blocking navigation
 * to the rest of the app until the user picks a personal password.
 */
final class Version20260521120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Manual user creation — users.password_change_required column';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE users ADD COLUMN IF NOT EXISTS password_change_required BOOLEAN NOT NULL DEFAULT false'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP COLUMN IF EXISTS password_change_required');
    }
}
