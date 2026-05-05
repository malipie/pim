<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Issue #438 — DAM MVP storage + indexing extensions for assets.
 *
 * Adds the columns the upload pipeline + thumbnail worker + dedupe + filters
 * rely on:
 *   - content_hash    SHA-256 hex used for tenant-scoped dedupe (UNIQUE
 *                     partial index, NULL skipped)
 *   - width/height    image dimensions captured at upload
 *   - page_count      PDF page count (NULL for non-PDF)
 *   - tags            JSONB array (GIN-indexed) for the chip-style metadata
 *   - thumbnails_status pending|ready|failed driver for the worker pipeline
 *
 * Indexes target the listing + filter use-cases:
 *   - (tenant_id, content_hash) UNIQUE — dedupe lookup on POST
 *   - (tenant_id, mime_type)            — MIME-group filter
 *   - (tenant_id, created_at DESC)      — default list ordering
 *   - GIN(tags)                         — tag chip filter
 *
 * Existing `assets_tenant_idx` covers the tenant-only path so the new
 * composite indexes complement rather than replace it.
 */
final class Version20260505192941 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'DAM MVP: content_hash, dimensions, page_count, tags, thumbnails_status + filter indexes (#438).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE assets
              ADD content_hash CHAR(64) DEFAULT NULL,
              ADD width INT DEFAULT NULL,
              ADD height INT DEFAULT NULL,
              ADD page_count INT DEFAULT NULL,
              ADD tags JSONB NOT NULL DEFAULT '[]'::jsonb,
              ADD thumbnails_status VARCHAR(16) NOT NULL DEFAULT 'pending'
        SQL);

        $this->addSql(<<<'SQL'
            CREATE UNIQUE INDEX assets_tenant_hash_uniq
              ON assets (tenant_id, content_hash)
              WHERE content_hash IS NOT NULL
        SQL);

        $this->addSql('CREATE INDEX assets_tenant_mime_idx ON assets (tenant_id, mime_type)');
        $this->addSql('CREATE INDEX assets_tenant_created_idx ON assets (tenant_id, created_at DESC)');
        $this->addSql('CREATE INDEX assets_tags_gin_idx ON assets USING gin (tags)');

        $this->addSql(<<<'SQL'
            ALTER TABLE assets
              ADD CONSTRAINT assets_thumbnails_status_chk
              CHECK (thumbnails_status IN ('pending', 'ready', 'failed'))
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE assets DROP CONSTRAINT assets_thumbnails_status_chk');
        $this->addSql('DROP INDEX assets_tags_gin_idx');
        $this->addSql('DROP INDEX assets_tenant_created_idx');
        $this->addSql('DROP INDEX assets_tenant_mime_idx');
        $this->addSql('DROP INDEX assets_tenant_hash_uniq');

        $this->addSql(<<<'SQL'
            ALTER TABLE assets
              DROP COLUMN thumbnails_status,
              DROP COLUMN tags,
              DROP COLUMN page_count,
              DROP COLUMN height,
              DROP COLUMN width,
              DROP COLUMN content_hash
        SQL);
    }
}
