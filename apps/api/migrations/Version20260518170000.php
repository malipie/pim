<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * RBAC-P2-005 (#654) — Postgres Row-Level Security activation on the
 * RBAC tenant-scoped tables as the second isolation layer (defence in
 * depth) behind Doctrine TenantFilter.
 *
 * RLS scope in this PR: the tables added by Phase 1 RBAC migrations —
 *   api_tokens, invitations, user_role_assignments,
 *   user_tenant_memberships, audit_logs (P1-005)
 *
 * Tables intentionally NOT covered today:
 *   - users, roles, permissions, role_permissions, attributes, etc. —
 *     these need a broader audit + benchmark before RLS goes on every
 *     tenant-scoped table in the system. Phase 6 #720 (final CI gates)
 *     is the natural home for the rollout sweep. The 5 RBAC-native
 *     tables here are the smallest unit that closes the Phase 2
 *     PRD §11.1a obligation ("RLS activation by Phase 2").
 *
 * Bypass policy: Super Admin operations set `app.is_super_admin = 'true'`
 * via `RlsContextListener` before issuing the cross-tenant query and
 * unset it immediately after. Without that flag, RLS strips every row
 * whose tenant_id does not match the session-local
 * `app.current_tenant`.
 *
 * Session variable wiring: TenantContextRebindingMiddleware (existing)
 * + RlsContextListener (new in this PR's services.yaml) run
 * `SET LOCAL app.current_tenant = '<uuid>'` at the start of every
 * request. With pgBouncer transaction-pooling this remains correct;
 * `SET LOCAL` is bound to the transaction so the next checkout
 * sees a fresh value.
 *
 * Performance: a single policy comparison per row is <1 µs; on the
 * RBAC tables (typical: 10s-100s of rows per tenant) the overhead
 * is unmeasurable. The full benchmark against 50k-row datasets is
 * deferred to Phase 6 #720 alongside the broader RLS rollout.
 */
final class Version20260518170000 extends AbstractMigration
{
    // user_role_assignments is intentionally excluded — it has no tenant_id
    // column (the user_id FK to a tenant-scoped users row supplies the
    // boundary). Trying to apply a tenant-isolation policy on it errors
    // with "column tenant_id does not exist" on every fresh database.
    // Phase 3 ticket follow-up adds a junction-aware policy if benchmarks
    // justify the extra layer; for now TenantFilter + the parent table's
    // RLS keep the join tenant-safe.
    private const array RLS_TABLES = [
        'api_tokens',
        'invitations',
        'user_tenant_memberships',
        'audit_logs',
    ];

    public function getDescription(): string
    {
        return 'RBAC-P2-005 — enable RLS + tenant-isolation policy on 4 RBAC tables (api_tokens, invitations, user_tenant_memberships, audit_logs)';
    }

    public function up(Schema $schema): void
    {
        // ── HOTFIX: Version20260518160000 created the special_flags GIN
        // index on a `json`-typed column. Postgres only supports GIN on
        // `jsonb`. The original migration succeeded locally (with
        // `doctrine:schema:update`-style relaxed checks) but fails the
        // strict `doctrine:migrations:migrate` step in the Playwright job.
        // Convert the column + recreate the index here in a single
        // transaction so the RLS migration block doesn't half-apply.
        $this->addSql('DROP INDEX IF EXISTS idx_audit_logs_special_flags');
        $this->addSql('ALTER TABLE audit_logs ALTER COLUMN special_flags TYPE JSONB USING special_flags::jsonb');
        $this->addSql('CREATE INDEX idx_audit_logs_special_flags ON audit_logs USING GIN (special_flags)');

        foreach (self::RLS_TABLES as $table) {
            // Audit_logs has nullable tenant_id (Super Admin cross-tenant ops).
            // The policy treats NULL tenant_id as "platform-level row" —
            // visible only when is_super_admin flag is set.
            $tenantClause = 'audit_logs' === $table
                ? 'tenant_id = current_setting(\'app.current_tenant\', true)::uuid OR (tenant_id IS NULL AND current_setting(\'app.is_super_admin\', true) = \'true\')'
                : 'tenant_id = current_setting(\'app.current_tenant\', true)::uuid';

            $this->addSql(\sprintf('ALTER TABLE %s ENABLE ROW LEVEL SECURITY', $table));
            $this->addSql(\sprintf(
                'CREATE POLICY tenant_isolation_%s ON %s USING (%s)',
                $table,
                $table,
                $tenantClause,
            ));
            $this->addSql(\sprintf(
                'CREATE POLICY super_admin_bypass_%s ON %s USING (current_setting(\'app.is_super_admin\', true) = \'true\')',
                $table,
                $table,
            ));
        }
    }

    public function down(Schema $schema): void
    {
        foreach (array_reverse(self::RLS_TABLES) as $table) {
            $this->addSql(\sprintf('DROP POLICY IF EXISTS super_admin_bypass_%s ON %s', $table, $table));
            $this->addSql(\sprintf('DROP POLICY IF EXISTS tenant_isolation_%s ON %s', $table, $table));
            $this->addSql(\sprintf('ALTER TABLE %s DISABLE ROW LEVEL SECURITY', $table));
        }
    }
}
