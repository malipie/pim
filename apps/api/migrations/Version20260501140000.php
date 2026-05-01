<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * UI-02.1 (#291) — denormalised list-view cache columns on `objects`.
 *
 * `completeness_pct SMALLINT 0..100` is the flat percentage rendered in
 * the products list `<CompletenessBadge>` (UI-02.10) and used by the
 * "Completeness <50%" filter chip (UI-02.9). Recomputed by
 * {@see \App\Catalog\Application\AttributesIndexedRebuilder} on every
 * single-edit flush of an `ObjectValue` via the existing
 * `AttributesIndexedSyncListener` two-phase flush wiring; the bulk path
 * stays async via the rebuild Messenger handler.
 *
 * `sync_status_aggregate VARCHAR(8) IN ('green','yellow','red','gray')`
 * collapses per-channel sync history to one badge for the
 * `<SyncAggregateIcon>` widget. Default `gray` (no sync history yet) —
 * the per-channel publish flow ships in Faza 1, so this column stays at
 * `gray` in MVP and the column simply *exists* so the list query can
 * SELECT it without join. Faza 1 listener will mutate it on
 * `ChannelSyncCompletedEvent` / `ChannelSyncFailedEvent` (UI-02.5 read
 * endpoint already serialises the field).
 *
 * Index `objects_tenant_kind_compl_idx` supports the filter chip
 * "Completeness <50%" + sort-by-completeness; the same shape as the
 * existing `objects_tenant_kind_idx` plus a trailing `completeness_pct`
 * for index-only scans on the products list.
 */
final class Version20260501140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'UI-02.1 cache columns on objects: completeness_pct + sync_status_aggregate (#291).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE objects
              ADD COLUMN completeness_pct SMALLINT NOT NULL DEFAULT 0,
              ADD COLUMN sync_status_aggregate VARCHAR(8) NOT NULL DEFAULT 'gray',
              ADD CONSTRAINT objects_completeness_pct_range CHECK (completeness_pct BETWEEN 0 AND 100),
              ADD CONSTRAINT objects_sync_status_aggregate_enum CHECK (sync_status_aggregate IN ('green','yellow','red','gray'))
            SQL);

        $this->addSql(<<<'SQL'
            CREATE INDEX objects_tenant_kind_compl_idx
              ON objects (tenant_id, kind, completeness_pct)
            SQL);

        $this->addSql(<<<'SQL'
            CREATE INDEX objects_tenant_kind_sync_aggr_idx
              ON objects (tenant_id, kind, sync_status_aggregate)
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS objects_tenant_kind_sync_aggr_idx');
        $this->addSql('DROP INDEX IF EXISTS objects_tenant_kind_compl_idx');
        $this->addSql('ALTER TABLE objects DROP CONSTRAINT IF EXISTS objects_sync_status_aggregate_enum');
        $this->addSql('ALTER TABLE objects DROP CONSTRAINT IF EXISTS objects_completeness_pct_range');
        $this->addSql('ALTER TABLE objects DROP COLUMN sync_status_aggregate');
        $this->addSql('ALTER TABLE objects DROP COLUMN completeness_pct');
    }
}
