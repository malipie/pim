<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * RBAC-P5-008 (#698) — adds `auto_grant_new_object_types` to roles.
 *
 * Flag toggled in the role builder UI; when set, the ObjectType
 * creation flow (epik 0.4) auto-grants the new type's `view + edit`
 * permissions to this role at flush time. The column defaults to
 * FALSE so existing seeded roles retain their explicit-grant behaviour
 * and the AppFixtures don't need to be retrofitted.
 */
final class Version20260520080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'RBAC-P5-008 — roles.auto_grant_new_object_types flag';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE roles
                ADD COLUMN auto_grant_new_object_types BOOLEAN NOT NULL DEFAULT FALSE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE roles DROP COLUMN auto_grant_new_object_types');
    }
}
