<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * CHC-02 (#1285) — object_channel_placements.
 *
 * Where a product lands in a channel's navigation tree. Separate from the
 * master `object_categories` pivot (different semantics). One placement per
 * (object, channel); `source` distinguishes manual vs auto-assigned (CHC-07).
 */
final class Version20260606130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'CHC-02: create object_channel_placements (product → channel navigation node)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE object_channel_placements (id UUID NOT NULL, tenant_id UUID NOT NULL, object_id UUID NOT NULL, channel_id UUID NOT NULL, channel_category_node_id UUID NOT NULL, source VARCHAR(16) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE object_channel_placements ADD CONSTRAINT ocp_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE object_channel_placements ADD CONSTRAINT ocp_object_fk FOREIGN KEY (object_id) REFERENCES objects (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE object_channel_placements ADD CONSTRAINT ocp_channel_fk FOREIGN KEY (channel_id) REFERENCES channels (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE object_channel_placements ADD CONSTRAINT ocp_node_fk FOREIGN KEY (channel_category_node_id) REFERENCES channel_category_nodes (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX object_channel_placements_tenant_object_channel_uniq ON object_channel_placements (tenant_id, object_id, channel_id)');
        $this->addSql('CREATE INDEX ocp_object_idx ON object_channel_placements (object_id)');
        $this->addSql('CREATE INDEX ocp_channel_idx ON object_channel_placements (channel_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE object_channel_placements');
    }
}
