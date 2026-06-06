<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * CHC-06 (#1289) — channel_category_node_mappings.
 *
 * One row per (tenant, channel, master category): the set of channel
 * navigation nodes a master category maps to (M:N on the channel side, stored
 * as a JSONB id array). Drives CHC-07 auto-assignment of products.
 */
final class Version20260606150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'CHC-06: create channel_category_node_mappings (master category → channel nodes)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE channel_category_node_mappings (id UUID NOT NULL, tenant_id UUID NOT NULL, channel_id UUID NOT NULL, master_cat_id UUID NOT NULL, channel_node_ids JSONB NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE channel_category_node_mappings ADD CONSTRAINT ccnm_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE channel_category_node_mappings ADD CONSTRAINT ccnm_channel_fk FOREIGN KEY (channel_id) REFERENCES channels (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE channel_category_node_mappings ADD CONSTRAINT ccnm_master_fk FOREIGN KEY (master_cat_id) REFERENCES objects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX ccnm_tenant_channel_master_uniq ON channel_category_node_mappings (tenant_id, channel_id, master_cat_id)');
        $this->addSql('CREATE INDEX ccnm_channel_idx ON channel_category_node_mappings (channel_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE channel_category_node_mappings');
    }
}
