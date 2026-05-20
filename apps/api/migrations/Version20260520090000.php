<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * RBAC-P5-007 (#697) — `role_attribute_permissions` table for 3-state
 * per-attribute grants overriding the role-level matrix.
 *
 * Permission level enum (text, not native enum for migration friendliness):
 *   - `view`       — read-only override; role can read this attribute
 *                    even if its module-level grant says `restricted`.
 *   - `edit`       — read + write override.
 *   - `restricted` — explicitly hidden from this role, regardless of
 *                    its module-level grant. Persisting `restricted`
 *                    rather than omitting the row lets the resolver
 *                    distinguish "no opinion, fall back to matrix"
 *                    from "explicit deny".
 *
 * No FK to `attributes(id)` because the Catalog bundle owns that table
 * and a cross-bundle FK would couple bounded contexts beyond what
 * ADR-009 allows; the resolver validates referenced attribute existence
 * at write time via the AttributeRepository.
 *
 * `role_id` FK is ON DELETE CASCADE — deleting a custom role wipes its
 * attribute overrides too, mirroring how `role_permissions` cascades.
 */
final class Version20260520090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'RBAC-P5-007 — role_attribute_permissions table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS role_attribute_permissions (
                id UUID NOT NULL,
                role_id UUID NOT NULL,
                attribute_id UUID NOT NULL,
                permission_level VARCHAR(16) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id),
                CONSTRAINT fk_role_attribute_permissions_role
                    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
                CONSTRAINT role_attribute_permissions_level_check
                    CHECK (permission_level IN ('view', 'edit', 'restricted'))
            )
        SQL);
        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX IF NOT EXISTS role_attribute_permissions_role_attr_uniq
                ON role_attribute_permissions (role_id, attribute_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IF NOT EXISTS role_attribute_permissions_role_idx
                ON role_attribute_permissions (role_id)
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX IF NOT EXISTS role_attribute_permissions_attr_idx
                ON role_attribute_permissions (attribute_id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE role_attribute_permissions');
    }
}
