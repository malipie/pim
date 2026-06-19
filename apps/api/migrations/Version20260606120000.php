<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * CHC-01 (#1284) — channel navigation-tree nodes.
 *
 * Channel category trees (Allegro / Shopify / BaseLinker navigation) get their
 * own table, separate from the master category tree on `objects`. Nodes carry
 * an `external_code` (the category id in the destination system) and an LTREE
 * `path` for fast descendant queries. `channels.category_tree_root_object_id`
 * stays a soft FK (no DB constraint) — its semantics shift from `objects.id`
 * to `channel_category_nodes.id`; the ChannelCategoryRootValidator enforces it.
 */
final class Version20260606120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'CHC-01: create channel_category_nodes (per-channel navigation tree, ltree path + external_code)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE channel_category_nodes (id UUID NOT NULL, tenant_id UUID NOT NULL, channel_id UUID NOT NULL, parent_id UUID DEFAULT NULL, code VARCHAR(128) NOT NULL, label JSONB NOT NULL, path LTREE NOT NULL, external_code VARCHAR(255) DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('ALTER TABLE channel_category_nodes ADD CONSTRAINT channel_category_nodes_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE channel_category_nodes ADD CONSTRAINT channel_category_nodes_channel_fk FOREIGN KEY (channel_id) REFERENCES channels (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE channel_category_nodes ADD CONSTRAINT channel_category_nodes_parent_fk FOREIGN KEY (parent_id) REFERENCES channel_category_nodes (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE UNIQUE INDEX channel_category_nodes_tenant_channel_code_uniq ON channel_category_nodes (tenant_id, channel_id, code)');
        $this->addSql('CREATE INDEX channel_category_nodes_channel_idx ON channel_category_nodes (channel_id)');
        $this->addSql('CREATE INDEX channel_category_nodes_path_gist_idx ON channel_category_nodes USING GIST (path)');

        // Reset the soft FK: existing values point at master `objects` (the
        // pre-CHC semantics). The new validator checks `channel_category_nodes`,
        // so any future edit of such a channel would otherwise fail. Operators
        // recreate channel roots through the navigation-tree endpoint.
        $this->addSql('UPDATE channels SET category_tree_root_object_id = NULL');
    }

    public function down(Schema $schema): void
    {
        // AUD-041: dropping `channel_category_nodes` is itself reversible, but
        // `up()` ALSO ran `UPDATE channels SET category_tree_root_object_id =
        // NULL` (re-pointing the soft FK from master objects to the new nav
        // tree). The pre-migration root ids are gone — `down()` cannot restore
        // them. Dropping the table alone would report a successful rollback
        // while the nulled roots stay lost (a false round-trip), so the reverse
        // is lossy: fail loud and require a restore from the pre-dump.
        $this->throwIrreversibleMigrationException(
            'up() nulled channels.category_tree_root_object_id for every channel and the prior values are not '
            .'recoverable; dropping channel_category_nodes alone does not restore them. Take a pre-dump BEFORE '
            .'this migration and restore from it instead — see docs/runbook/destructive-migrations.md.',
        );
    }
}
