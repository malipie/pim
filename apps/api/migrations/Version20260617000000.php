<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * AUD-027 / W1-2 — unify the RLS GUC name on `refresh_tokens` to
 * `app.current_tenant`.
 *
 * The application sets exactly one Postgres session variable for the tenant
 * boundary: `app.current_tenant` — wired by {@see
 * \App\Identity\Infrastructure\Doctrine\RlsContextListener} on every HTTP
 * request and by {@see \App\Shared\Infrastructure\Messenger\TenantRlsGucMiddleware}
 * in every async worker. Every RLS policy created since IMP2-2.x
 * (api_tokens, audit_logs, import_logs, import_staged_files, import_undo_log,
 * invitations, user_tenant_memberships) reads that same key.
 *
 * `refresh_tokens` is the lone survivor of the first RLS wave
 * ({@see Version20260428195217}, which seeded `products` + `refresh_tokens`
 * with `pim.current_tenant_id`). The `products` policies were dropped when
 * that table was migrated to `objects` ({@see Version20260428222056}), but
 * the `refresh_tokens` policies kept the legacy GUC name the code never sets.
 *
 * Consequence (the bug this migration fixes): once FORCE ROW LEVEL SECURITY
 * lands (AUD-002 / W1-1), the `refresh_tokens` policy compares
 * `tenant_id = current_setting('pim.current_tenant_id', true)::uuid`, which is
 * always NULL (the GUC is never set) → three-valued logic denies every row →
 * the refresh-token login flow breaks for every tenant. This migration is the
 * explicit precondition for W1-1.
 *
 * Scope: this migration only renames the GUC. It does NOT enable or force RLS
 * on `refresh_tokens` — `relrowsecurity`/`relforcerowsecurity` stay untouched
 * so application behaviour is identical to before (FORCE activation is W1-1).
 *
 * Reversibility: `down()` restores the legacy `pim.current_tenant_id` policies
 * verbatim so the schema round-trips cleanly.
 */
final class Version20260617000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'AUD-027: unify refresh_tokens RLS policies on the app.current_tenant GUC (was pim.current_tenant_id).';
    }

    public function up(Schema $schema): void
    {
        // Drop the legacy-GUC policies (idempotent: IF EXISTS guards re-runs
        // and any environment where they were already migrated by hand).
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation_select ON refresh_tokens');
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation_insert ON refresh_tokens');
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation_update ON refresh_tokens');
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation_delete ON refresh_tokens');

        // Recreate them against the canonical `app.current_tenant` GUC,
        // keeping the SELECT/INSERT/UPDATE/DELETE split + WITH CHECK exactly
        // as Version20260428195217 defined them.
        $this->addSql("CREATE POLICY tenant_isolation_select ON refresh_tokens FOR SELECT USING (tenant_id = current_setting('app.current_tenant', true)::uuid)");
        $this->addSql("CREATE POLICY tenant_isolation_insert ON refresh_tokens FOR INSERT WITH CHECK (tenant_id = current_setting('app.current_tenant', true)::uuid)");
        $this->addSql("CREATE POLICY tenant_isolation_update ON refresh_tokens FOR UPDATE USING (tenant_id = current_setting('app.current_tenant', true)::uuid) WITH CHECK (tenant_id = current_setting('app.current_tenant', true)::uuid)");
        $this->addSql("CREATE POLICY tenant_isolation_delete ON refresh_tokens FOR DELETE USING (tenant_id = current_setting('app.current_tenant', true)::uuid)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation_select ON refresh_tokens');
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation_insert ON refresh_tokens');
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation_update ON refresh_tokens');
        $this->addSql('DROP POLICY IF EXISTS tenant_isolation_delete ON refresh_tokens');

        $this->addSql("CREATE POLICY tenant_isolation_select ON refresh_tokens FOR SELECT USING (tenant_id = current_setting('pim.current_tenant_id', true)::uuid)");
        $this->addSql("CREATE POLICY tenant_isolation_insert ON refresh_tokens FOR INSERT WITH CHECK (tenant_id = current_setting('pim.current_tenant_id', true)::uuid)");
        $this->addSql("CREATE POLICY tenant_isolation_update ON refresh_tokens FOR UPDATE USING (tenant_id = current_setting('pim.current_tenant_id', true)::uuid) WITH CHECK (tenant_id = current_setting('pim.current_tenant_id', true)::uuid)");
        $this->addSql("CREATE POLICY tenant_isolation_delete ON refresh_tokens FOR DELETE USING (tenant_id = current_setting('pim.current_tenant_id', true)::uuid)");
    }
}
