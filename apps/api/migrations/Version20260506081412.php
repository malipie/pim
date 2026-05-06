<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Issue #440 — Multimedia tab in product detail.
 *
 * Adds two pieces:
 *   1. `assets.folder_code` — opt-in logical folder. NULL means root,
 *      "product-<UUID>" identifies files uploaded inside a product card.
 *   2. `product_assets` — m2m link between products (CatalogObject of
 *      kind=product) and Asset rows. The same Asset can be reused on
 *      multiple products via the picker without duplicating bytes;
 *      `folder_code` records *where* the file physically belongs (its
 *      origin), the m2m records *which* products show it.
 *
 * Both FK columns cascade on parent delete: dropping the product or
 * the asset clears every link.
 */
final class Version20260506081412 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Multimedia tab: assets.folder_code + product_assets m2m (#440).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE assets ADD folder_code VARCHAR(128) DEFAULT NULL');
        $this->addSql(<<<'SQL'
            CREATE INDEX assets_tenant_folder_idx
              ON assets (tenant_id, folder_code)
              WHERE folder_code IS NOT NULL
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE product_assets (
              asset_id UUID NOT NULL,
              product_id UUID NOT NULL,
              position INT DEFAULT NULL,
              created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              PRIMARY KEY (asset_id, product_id)
            )
        SQL);
        $this->addSql('CREATE INDEX product_assets_product_idx ON product_assets (product_id, position)');
        $this->addSql('ALTER TABLE product_assets ADD CONSTRAINT product_assets_asset_fk FOREIGN KEY (asset_id) REFERENCES assets (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE product_assets ADD CONSTRAINT product_assets_product_fk FOREIGN KEY (product_id) REFERENCES objects (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE product_assets DROP CONSTRAINT product_assets_product_fk');
        $this->addSql('ALTER TABLE product_assets DROP CONSTRAINT product_assets_asset_fk');
        $this->addSql('DROP TABLE product_assets');

        $this->addSql('DROP INDEX assets_tenant_folder_idx');
        $this->addSql('ALTER TABLE assets DROP COLUMN folder_code');
    }
}
