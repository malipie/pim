<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * UI-02.7 (#297) — `saved_views` table for per-user list state
 * persistence (filters / sort / visible columns / page size /
 * variants_mode) consumed by `<SavedViewsDropdown>` (UI-02.15).
 *
 * Solo per-user views in MVP. Sharing / templates land in Faza 1+
 * (proposed ADR-013). The unique `(tenant_id, user_id, slug)` keeps
 * collisions scoped to the owning user — sharing later only needs to
 * relax the index, not rebuild the row.
 */
final class Version20260501170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'UI-02.7 saved_views table (#297).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE saved_views (
              id UUID NOT NULL,
              tenant_id UUID NOT NULL,
              user_id UUID NULL,
              slug VARCHAR(255) NOT NULL,
              name VARCHAR(255) NOT NULL,
              description TEXT NULL,
              resource VARCHAR(64) NOT NULL DEFAULT 'products',
              config JSONB NOT NULL DEFAULT '{}',
              is_default BOOLEAN NOT NULL DEFAULT false,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (id),
              CONSTRAINT saved_views_tenant_user_slug_uniq UNIQUE (tenant_id, user_id, slug)
            )
            SQL);

        $this->addSql('CREATE INDEX saved_views_tenant_user_resource_idx ON saved_views (tenant_id, user_id, resource)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS saved_views_tenant_user_resource_idx');
        $this->addSql('DROP TABLE IF EXISTS saved_views');
    }
}
