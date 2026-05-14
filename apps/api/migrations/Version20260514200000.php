<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * VIEW-38 (#579) — `attributes.is_filterable` boolean.
 *
 * Operator-controlled flag that turns an attribute into a top-level
 * filter target on the catalog Meilisearch index. Set via the
 * `Settings → Attributes` UI; persisted by `AttributeFilterableListener`
 * which reprovisions the index settings on change. Backfill seeds
 * `true` for the codes the previous static `filterableAttributes` list
 * carried so the existing advanced filter panel UI keeps working
 * without an extra migration step.
 *
 * Partial index `WHERE is_filterable = true` keeps lookups fast in
 * `DoctrineAttributeRepository::filterableCodes()` (called on every
 * index settings refresh).
 */
final class Version20260514200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'attributes.is_filterable boolean + backfill for current advanced-filter UI codes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE attributes ADD COLUMN is_filterable BOOLEAN NOT NULL DEFAULT false");
        $this->addSql("CREATE INDEX attributes_is_filterable_idx ON attributes (is_filterable) WHERE is_filterable = true");
        $this->addSql("UPDATE attributes SET is_filterable = true WHERE code IN ('brand', 'category', 'price', 'stock', 'tags', 'main_image', 'color', 'size', 'in_stock', 'release_date', 'completeness_pct')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP INDEX IF EXISTS attributes_is_filterable_idx");
        $this->addSql("ALTER TABLE attributes DROP COLUMN IF EXISTS is_filterable");
    }
}
