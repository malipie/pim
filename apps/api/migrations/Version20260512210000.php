<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * HARD-01 — composite index on `objects (tenant_id, parent_id)` for
 * tree-mode product list (PR #514) and category-children listing.
 *
 * The plain `objects_parent_idx` from #38 (Version20260428220053) only
 * indexes `parent_id`, so a query like
 *   SELECT id FROM objects WHERE parent_id = :master AND tenant_id = :t
 * scans every row across every tenant, then filters. With 50k SKU and
 * 100 variants per master that's measurable seconds of latency.
 *
 * The composite index lets the planner satisfy both predicates from a
 * single scan. Postgres-only syntax; works with the production target
 * (PostgreSQL 16) per CLAUDE.md stack pin.
 */
final class Version20260512210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'HARD-01: composite index objects (tenant_id, parent_id) for tree-mode listing.';
    }

    public function up(Schema $schema): void
    {
        // CONCURRENTLY would avoid the table-level lock but Doctrine
        // wraps migrations in a transaction; concurrent index creation
        // requires manual ops outside this tooling. For dev/staging the
        // blocking lock is acceptable; for prod the runbook should run
        // this CREATE INDEX CONCURRENTLY by hand and then mark the
        // migration executed (`doctrine:migrations:version --add`).
        $this->addSql('CREATE INDEX objects_tenant_parent_idx ON objects (tenant_id, parent_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS objects_tenant_parent_idx');
    }
}
