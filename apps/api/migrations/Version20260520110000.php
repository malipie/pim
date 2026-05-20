<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Role editor polish (marathon-3 follow-up) — adds `roles.description`
 * so the custom-role builder can persist the operator-supplied
 * description shown in PRD-PIM-rbac §5.3 mockup.
 *
 * TEXT (not VARCHAR) because the field can legitimately carry a
 * multi-line explanation of why the role exists.
 */
final class Version20260520110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Role editor polish — roles.description column';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE roles ADD COLUMN IF NOT EXISTS description TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE roles DROP COLUMN IF EXISTS description');
    }
}
