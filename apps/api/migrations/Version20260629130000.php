<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * APIC-P3-09 (ADR-0022, epic APIC) — add the `next_run` column to
 * `integration_sync_bindings` so the schedule dispatcher can persist (and the
 * FE can surface) the next jittered fire time of every scheduled binding. A
 * `(tenant_id, enabled, next_run)` index backs the per-tenant due-bindings scan.
 *
 * No RLS change: the column lives on the existing FORCE-RLS table created in
 * APIC-P3-01.
 */
final class Version20260629130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'APIC-P3-09: add integration_sync_bindings.next_run + due index for the schedule dispatcher.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE integration_sync_bindings ADD next_run TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE INDEX integration_sync_bindings_due_idx ON integration_sync_bindings (tenant_id, enabled, next_run)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX integration_sync_bindings_due_idx');
        $this->addSql('ALTER TABLE integration_sync_bindings DROP next_run');
    }
}
