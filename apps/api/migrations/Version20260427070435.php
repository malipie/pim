<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Sprint 0 baseline schema — Tenant + Product with the multi-tenancy invariant
 * (tenant_id NOT NULL on every domain row, ADR-003). The unique index on
 * (tenant_id, sku) keeps SKU collisions scoped to a single tenant; cross-tenant
 * SKU duplication is intentional.
 */
final class Version20260427070435 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tenants and products tables with tenant_id FK and unique (tenant_id, sku) index.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE tenants (id UUID NOT NULL, code VARCHAR(64) NOT NULL, name VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX tenants_code_uniq ON tenants (code)');

        $this->addSql('CREATE TABLE products (id UUID NOT NULL, tenant_id UUID NOT NULL, sku VARCHAR(64) NOT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, brand VARCHAR(128) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX products_tenant_idx ON products (tenant_id)');
        $this->addSql('CREATE UNIQUE INDEX products_tenant_sku_uniq ON products (tenant_id, sku)');
        $this->addSql('ALTER TABLE products ADD CONSTRAINT products_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE RESTRICT NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE products DROP CONSTRAINT products_tenant_fk');
        $this->addSql('DROP TABLE products');
        $this->addSql('DROP TABLE tenants');
    }
}
