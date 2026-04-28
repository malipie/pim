<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Epic 0.2 / ticket #30 — Postgres RLS policies for tenant isolation.
 *
 * Creates SELECT/INSERT/UPDATE/DELETE policies on every tenant-scoped table
 * but does NOT activate row-level security (`ENABLE ROW LEVEL SECURITY` is
 * intentionally absent). Postgres treats `CREATE POLICY` on a table without
 * RLS enabled as inert — the policy is stored and ready, but every query
 * runs as if no policy existed. This keeps MVP behaviour unchanged while
 * cutting phase-2 activation work to a single ALTER TABLE per table.
 *
 * Policy contract: `tenant_id = current_setting('pim.current_tenant_id',
 * true)::uuid`. The `true` (`missing_ok`) flag returns NULL when the GUC is
 * not set, and `tenant_id = NULL` is false (three-valued logic) — every row
 * is denied until the application sets `SET LOCAL pim.current_tenant_id =
 * '...'` at transaction start. Phase 2 wires that into the request lifecycle.
 *
 * Tables covered:
 *   - products: domain table, primary candidate.
 *   - refresh_tokens: also tenant-scoped; lookup is by token_hash so phase 2
 *     will need to decide whether RLS applies (caller has no current tenant
 *     when refreshing). Policy is included here for symmetry — easier to
 *     drop one in phase 2 than to backfill it.
 *
 * Tables intentionally excluded:
 *   - users: login flow looks up a user by email *before* the tenant is
 *     known. Activating RLS on users would require a different mechanism
 *     (e.g. SECURITY DEFINER function) and lands in phase 2.
 *   - roles: nullable tenant_id (built-in roles are global with NULL).
 *     A naive `tenant_id = X` policy would hide built-ins.
 *   - permissions, junctions, infra tables (messenger_messages,
 *     doctrine_migration_versions): no tenant_id column.
 *
 * See `docs/multi-tenancy.md` for the rollout plan and `pim:tenant:audit`
 * for a CLI that confirms every domain table carries `tenant_id`.
 */
final class Version20260428195217 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add RLS policies on tenant-scoped tables (policies present, RLS not enabled).';
    }

    public function up(Schema $schema): void
    {
        foreach (self::tables() as $table) {
            $this->addSql(\sprintf(
                "CREATE POLICY tenant_isolation_select ON %s FOR SELECT USING (tenant_id = current_setting('pim.current_tenant_id', true)::uuid)",
                $table,
            ));
            $this->addSql(\sprintf(
                "CREATE POLICY tenant_isolation_insert ON %s FOR INSERT WITH CHECK (tenant_id = current_setting('pim.current_tenant_id', true)::uuid)",
                $table,
            ));
            $this->addSql(\sprintf(
                "CREATE POLICY tenant_isolation_update ON %s FOR UPDATE USING (tenant_id = current_setting('pim.current_tenant_id', true)::uuid) WITH CHECK (tenant_id = current_setting('pim.current_tenant_id', true)::uuid)",
                $table,
            ));
            $this->addSql(\sprintf(
                "CREATE POLICY tenant_isolation_delete ON %s FOR DELETE USING (tenant_id = current_setting('pim.current_tenant_id', true)::uuid)",
                $table,
            ));
        }

        // Phase-2 activation snippet (kept here as runnable documentation —
        // commented out so the migration is a no-op at runtime in MVP):
        //
        //   ALTER TABLE products       ENABLE ROW LEVEL SECURITY;
        //   ALTER TABLE products       FORCE  ROW LEVEL SECURITY;
        //   ALTER TABLE refresh_tokens ENABLE ROW LEVEL SECURITY;
        //   ALTER TABLE refresh_tokens FORCE  ROW LEVEL SECURITY;
        //
        // Phase 2 will land these in a separate migration so the audit trail
        // shows when isolation went hot.
    }

    public function down(Schema $schema): void
    {
        foreach (self::tables() as $table) {
            $this->addSql(\sprintf('DROP POLICY IF EXISTS tenant_isolation_delete ON %s', $table));
            $this->addSql(\sprintf('DROP POLICY IF EXISTS tenant_isolation_update ON %s', $table));
            $this->addSql(\sprintf('DROP POLICY IF EXISTS tenant_isolation_insert ON %s', $table));
            $this->addSql(\sprintf('DROP POLICY IF EXISTS tenant_isolation_select ON %s', $table));
        }
    }

    /**
     * @return list<string>
     */
    private static function tables(): array
    {
        return ['products', 'refresh_tokens'];
    }
}
