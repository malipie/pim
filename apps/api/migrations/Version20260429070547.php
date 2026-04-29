<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Epic 0.3 / ticket #37 — Asset + AssetVariant tables.
 *
 * Per ADR-009 the Asset bounded context owns storage details (path on
 * the Flysystem bucket, mime type, size, EXIF JSONB), while user-defined
 * metadata flows through `objects` + `object_values` via the optional
 * `object_id` FK. The split keeps EXIF + storage out of the EAV layer
 * (where they would be a poor fit) but lets admins attach localised
 * captions / alt text the same way they do for products.
 *
 * Variants table is empty in MVP except for the `original` row created
 * at upload time — phase 1 transformations land alongside the worker
 * pipeline.
 */
final class Version20260429070547 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add assets + asset_variants tables (#37).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE assets (
              id UUID NOT NULL,
              tenant_id UUID NOT NULL,
              object_id UUID DEFAULT NULL,
              code VARCHAR(128) NOT NULL,
              original_filename VARCHAR(255) NOT NULL,
              mime_type VARCHAR(128) NOT NULL,
              size BIGINT NOT NULL,
              metadata JSONB DEFAULT '{}' NOT NULL,
              storage_path VARCHAR(1024) NOT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX assets_tenant_idx ON assets (tenant_id)');
        $this->addSql('CREATE UNIQUE INDEX assets_tenant_code_uniq ON assets (tenant_id, code)');
        $this->addSql('CREATE UNIQUE INDEX assets_object_id_uniq ON assets (object_id)');
        $this->addSql('ALTER TABLE assets ADD CONSTRAINT assets_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT NOT DEFERRABLE');
        $this->addSql('ALTER TABLE assets ADD CONSTRAINT assets_object_fk FOREIGN KEY (object_id) REFERENCES objects (id) ON DELETE SET NULL NOT DEFERRABLE');

        $this->addSql(<<<'SQL'
            CREATE TABLE asset_variants (
              id UUID NOT NULL,
              asset_id UUID NOT NULL,
              variant_code VARCHAR(32) NOT NULL,
              storage_path VARCHAR(1024) NOT NULL,
              mime_type VARCHAR(128) NOT NULL,
              size BIGINT NOT NULL,
              PRIMARY KEY (id)
            )
        SQL);
        $this->addSql('CREATE INDEX asset_variants_asset_idx ON asset_variants (asset_id)');
        $this->addSql('CREATE UNIQUE INDEX asset_variants_asset_code_uniq ON asset_variants (asset_id, variant_code)');
        $this->addSql('ALTER TABLE asset_variants ADD CONSTRAINT asset_variants_asset_fk FOREIGN KEY (asset_id) REFERENCES assets (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE asset_variants DROP CONSTRAINT asset_variants_asset_fk');
        $this->addSql('DROP TABLE asset_variants');

        $this->addSql('ALTER TABLE assets DROP CONSTRAINT assets_object_fk');
        $this->addSql('ALTER TABLE assets DROP CONSTRAINT assets_tenant_fk');
        $this->addSql('DROP TABLE assets');
    }
}
