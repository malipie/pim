<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * VIEW-27 (#558) — `user_filter_favorites` table (PRD §5.1).
 *
 * Per-user „top N" attribute shortcut list for the `<AttributePicker>`.
 * Composite PK `(user_id, attribute_id)` makes the same attribute
 * impossible to favorite twice. `sort_order` keeps the operator's
 * preferred ordering (drag-to-reorder lands in Faza 1).
 *
 * Tenant scope inherited via `users.tenant_id` (FK CASCADE on user
 * delete; CASCADE on attribute delete keeps the table free of dangling
 * rows when an attribute is decommissioned). Listed in
 * `TenantAuditCommand::INFRA_TABLES`.
 */
final class Version20260514150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'VIEW-27 user_filter_favorites — per-user attribute favorites (#558).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE user_filter_favorites (
              user_id UUID NOT NULL,
              attribute_id UUID NOT NULL,
              sort_order INTEGER NOT NULL,
              PRIMARY KEY (user_id, attribute_id)
            )
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_filter_favorites
              ADD CONSTRAINT fk_uff_user
              FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE user_filter_favorites
              ADD CONSTRAINT fk_uff_attribute
              FOREIGN KEY (attribute_id) REFERENCES attributes(id) ON DELETE CASCADE
            SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_uff_user_order ON user_filter_favorites (user_id, sort_order)
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS user_filter_favorites');
    }
}
