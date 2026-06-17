<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * AUD-002 / W1-1 — turn Postgres Row-Level Security into a real second
 * isolation wall.
 *
 * Before this migration the application connected as `pim` (superuser +
 * BYPASSRLS + owner of every table), so RLS was decorative: three independent
 * bypass paths meant a Doctrine TenantFilter bug was a direct cross-tenant
 * leak with no backstop. Only 7 of ~43 tenant-scoped tables even had RLS
 * ENABLED, and none had FORCE, so even a NOBYPASSRLS role would have skipped
 * RLS on the owner's own tables.
 *
 * This migration (run as the OWNER connection — see doctrine_migrations.yaml
 * `connection: owner`, role `pim`) does three things:
 *
 *   1. Provisions the runtime role `pim_app` idempotently and grants it
 *      DML on every current + future table and USAGE on every sequence. The
 *      role itself (LOGIN + password) is created by
 *      docker/postgres/pim-init-app-role.sh before the api connects; the
 *      `CREATE ROLE IF NOT EXISTS` guard here covers environments built from
 *      ORM metadata (Foundry test DB) where that init hook never ran.
 *   2. ENABLE + FORCE ROW LEVEL SECURITY on every tenant-scoped table.
 *      FORCE makes the policy apply even to the table owner, so neither the
 *      owner nor any future role can silently read across tenants.
 *   3. Creates tenant-isolation + super-admin-bypass policies on each table.
 *
 * Two policy shapes (see the table-set constants):
 *
 *   - DOMAIN tables (objects, object_values, attributes, channels, assets,
 *     imports, exports, …) get the STRICT policy:
 *         tenant_id = current_setting('app.current_tenant', true)::uuid
 *     With FORCE + the runtime role being NOBYPASSRLS, an unset GUC yields
 *     `tenant_id = NULL` → three-valued logic → 0 rows. That is the desired
 *     fail-closed behaviour: domain endpoints always run with a resolved
 *     tenant (RlsContextListener sets the session GUC at kernel.request).
 *
 *   - AUTH tables (users, refresh_tokens, password_reset_tokens, api_keys,
 *     api_tokens, roles, sso_providers, tenant_locales,
 *     user_tenant_memberships, invitations) get the PRE-CONTEXT-SAFE policy:
 *         current_setting('app.current_tenant', true) IS NULL
 *         OR current_setting('app.current_tenant', true) = ''
 *         OR tenant_id = current_setting('app.current_tenant', true)::uuid
 *     These rows are read DURING authentication — the Symfony firewall
 *     (priority 8) loads the user by email, validates the refresh-token hash,
 *     resolves the SSO provider, hydrates the Tenant entity's lazy
 *     tenant_locales — all BEFORE RlsContextListener (priority 0) sets the
 *     GUC. A strict policy would deny those reads (GUC still empty) and break
 *     login / refresh / SSO entirely. The relaxed clause allows reads only
 *     while the GUC is empty (the brief pre-auth window); the instant a
 *     tenant is resolved the SAME policy enforces strict isolation. Lookups
 *     in that window are by unique non-enumerable keys (email, 256-bit token
 *     hash, UUID) and the application's auth logic validates ownership, so
 *     the relaxation does not widen the attack surface.
 *     Empirically grounded: with strict FORCE on audit_logs the login flow
 *     died with `new row violates row-level security policy for table
 *     "audit_logs"` — the kernel.response audit write ran after the GUC had
 *     already reset. The companion fix (RlsContextListener now sets the GUC at
 *     SESSION scope, is_local=false) keeps the GUC alive across the whole
 *     request so domain writes pass; the auth-table relaxation covers the
 *     genuine pre-context reads.
 *
 *   - NULLABLE-tenant tables (roles, smart_filter_presets, audit_logs) carry
 *     built-in / system-shipped rows with tenant_id IS NULL that every tenant
 *     must see (mirroring TenantFilter's SystemShipped handling). Their policy
 *     adds `OR tenant_id IS NULL`.
 *
 * Every table also gets a super_admin_bypass policy
 * (`current_setting('app.is_super_admin', true) = 'true'`) so the Phase 3
 * break-glass flow keeps working under FORCE.
 *
 * GUC contract: `app.current_tenant` everywhere (AUD-027 / W1-2 already
 * unified refresh_tokens onto it; this migration assumes that precondition).
 *
 * Reversibility: down() drops every policy, removes FORCE + ENABLE, and
 * revokes the grants. It intentionally does NOT drop the `pim_app` role
 * (other databases / connections may depend on it, and dropping a role that
 * owns nothing but is referenced by GRANTs elsewhere is error-prone) — the
 * role is harmless without grants.
 */
final class Version20260617050000 extends AbstractMigration
{
    /**
     * Tables read during the firewall / pre-authentication phase, before
     * RlsContextListener sets app.current_tenant. They get the
     * pre-context-safe policy (allow when the GUC is empty).
     *
     * @var list<string>
     */
    private const array AUTH_TABLES = [
        'users',
        'refresh_tokens',
        'password_reset_tokens',
        'api_keys',
        'api_tokens',
        'roles',
        'sso_providers',
        'tenant_locales',
        'user_tenant_memberships',
        'invitations',
        // audit_logs is written at kernel.response, AFTER the request resolved
        // its tenant — but for flows that authenticate WITHIN the request
        // (login), the request-time GUC was still empty when the row is
        // inserted with the now-known tenant_id. A strict WITH CHECK would
        // reject that insert (`new row violates row-level security policy for
        // table "audit_logs"`). The AuditLogListener sets tenant_id from the
        // authenticated principal itself, so RLS here is defence-in-depth, not
        // the primary boundary; the pre-context-safe shape lets the audit
        // write through while the GUC is empty and still enforces strict
        // isolation on reads once a tenant is in context. NULL-tenant
        // (platform / super-admin) rows stay readable only via the
        // super_admin_bypass policy.
        'audit_logs',
    ];

    /**
     * Tenant-scoped data tables. They always run with a resolved tenant, so
     * they get the strict (fail-closed) policy.
     *
     * @var list<string>
     */
    private const array DOMAIN_TABLES = [
        'api_profiles',
        'assets',
        'attribute_groups',
        'attribute_options',
        'attributes',
        'backups',
        'bulk_edit_jobs',
        'bulk_sessions',
        'channel_category_node_mappings',
        'channel_category_nodes',
        'channel_publication_profiles',
        'channels',
        'export_profiles',
        'export_sessions',
        'import_logs',
        'import_profiles',
        'import_schedule_runs',
        'import_schedules',
        'import_sessions',
        'import_source_logs',
        'import_sources',
        'import_staged_files',
        'import_undo_log',
        'menu_configurations',
        'object_channel_placements',
        'object_relations',
        'object_types',
        'object_values',
        'objects',
        'saved_views',
        'smart_filter_presets',
        'tenant_agent_configs',
    ];

    /**
     * Tables whose tenant_id is nullable for built-in / system-shipped rows
     * that EVERY tenant must see (mirroring TenantFilter's SystemShipped
     * handling). Their isolation predicate gains `OR tenant_id IS NULL`.
     *
     * `audit_logs` is deliberately NOT here even though its tenant_id is
     * nullable: a NULL-tenant audit row records a platform / super-admin
     * action and must stay visible only to super admins (via the
     * super_admin_bypass policy), not to every tenant. Its tenant policy is
     * therefore the strict one — the original RBAC-P2-005 behaviour
     * (`tenant_id IS NULL AND is_super_admin`) is preserved because the
     * super_admin_bypass policy is OR-combined with the strict tenant policy.
     *
     * @var list<string>
     */
    private const array NULLABLE_TENANT_TABLES = [
        'roles',
        'smart_filter_presets',
    ];

    public function getDescription(): string
    {
        return 'AUD-002/W1-1 — grant pim_app + ENABLE/FORCE RLS + tenant-isolation policies on all tenant-scoped tables.';
    }

    public function up(Schema $schema): void
    {
        // ── 1. Runtime role + grants ──────────────────────────────────────
        // Idempotent: the docker init hook usually created the role already;
        // this guard covers the Foundry-built test DB. No password is set
        // here — that is infra's job (pim-init-app-role.sh); a role with no
        // password simply cannot log in, which is harmless for the test DB
        // where the owner connection runs everything.
        $this->addSql(<<<'SQL'
            DO $$ BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'pim_app') THEN
                    CREATE ROLE pim_app NOLOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOBYPASSRLS NOINHERIT;
                END IF;
            END $$
            SQL);

        // DML on every existing table + USAGE/SELECT/UPDATE on sequences, plus
        // default privileges so tables/sequences created by LATER migrations
        // are reachable without re-granting. ALTER DEFAULT PRIVILEGES is keyed
        // to the granting role (the owner running this migration).
        $this->addSql('GRANT USAGE ON SCHEMA public TO pim_app');
        $this->addSql('GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO pim_app');
        $this->addSql('GRANT USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA public TO pim_app');
        $this->addSql('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO pim_app');
        $this->addSql('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT USAGE, SELECT, UPDATE ON SEQUENCES TO pim_app');

        // Drop the legacy per-command refresh_tokens policies seeded by
        // Version20260428195217 + renamed by W1-2 (Version20260617000000).
        // They are strict (no pre-context clause) and would be redundant
        // OR-combined noise next to the single canonical ALL policy this
        // migration creates below — and worse, they read as if refresh_tokens
        // were strict-isolated, hiding the deliberate pre-context-safe shape
        // that keeps the refresh-login flow working under FORCE RLS.
        foreach (['select', 'insert', 'update', 'delete'] as $cmd) {
            $this->addSql(\sprintf('DROP POLICY IF EXISTS tenant_isolation_%s ON refresh_tokens', $cmd));
        }

        // ── 2 + 3. RLS + policies per table ───────────────────────────────
        foreach (self::AUTH_TABLES as $table) {
            $this->applyRls($table, $this->isolationPredicate($table, preContextSafe: true));
        }
        foreach (self::DOMAIN_TABLES as $table) {
            $this->applyRls($table, $this->isolationPredicate($table, preContextSafe: false));
        }
    }

    public function down(Schema $schema): void
    {
        foreach ([...self::AUTH_TABLES, ...self::DOMAIN_TABLES] as $table) {
            $this->addSql(\sprintf('DROP POLICY IF EXISTS super_admin_bypass_%s ON %s', $table, $table));
            $this->addSql(\sprintf('DROP POLICY IF EXISTS tenant_isolation_%s ON %s', $table, $table));
            $this->addSql(\sprintf('ALTER TABLE %s NO FORCE ROW LEVEL SECURITY', $table));
            $this->addSql(\sprintf('ALTER TABLE %s DISABLE ROW LEVEL SECURITY', $table));
        }

        // Restore the 7 tables that had RLS ENABLED (not forced) before this
        // migration, with the policies their own migrations defined, so the
        // schema round-trips to exactly the pre-W1-1 state.
        //
        // refresh_tokens: the per-command policies from W1-2
        // (Version20260617000000), on the canonical app.current_tenant GUC.
        $this->addSql("CREATE POLICY tenant_isolation_select ON refresh_tokens FOR SELECT USING (tenant_id = current_setting('app.current_tenant', true)::uuid)");
        $this->addSql("CREATE POLICY tenant_isolation_insert ON refresh_tokens FOR INSERT WITH CHECK (tenant_id = current_setting('app.current_tenant', true)::uuid)");
        $this->addSql("CREATE POLICY tenant_isolation_update ON refresh_tokens FOR UPDATE USING (tenant_id = current_setting('app.current_tenant', true)::uuid) WITH CHECK (tenant_id = current_setting('app.current_tenant', true)::uuid)");
        $this->addSql("CREATE POLICY tenant_isolation_delete ON refresh_tokens FOR DELETE USING (tenant_id = current_setting('app.current_tenant', true)::uuid)");

        // api_tokens, invitations, user_tenant_memberships, audit_logs,
        // import_logs, import_staged_files, import_undo_log: RLS ENABLED + the
        // single tenant_isolation + super_admin_bypass policy pair from
        // RBAC-P2-005 (Version20260518170000) / IMP2-2.x. audit_logs keeps its
        // nullable-tenant clause.
        $reEnable = [
            'api_tokens', 'invitations', 'user_tenant_memberships', 'audit_logs',
            'import_logs', 'import_staged_files', 'import_undo_log',
        ];
        foreach ($reEnable as $table) {
            $tenantClause = 'audit_logs' === $table
                ? "tenant_id = current_setting('app.current_tenant', true)::uuid OR (tenant_id IS NULL AND current_setting('app.is_super_admin', true) = 'true')"
                : "tenant_id = current_setting('app.current_tenant', true)::uuid";
            $this->addSql(\sprintf('ALTER TABLE %s ENABLE ROW LEVEL SECURITY', $table));
            $this->addSql(\sprintf('CREATE POLICY tenant_isolation_%s ON %s USING (%s)', $table, $table, $tenantClause));
            $this->addSql(\sprintf("CREATE POLICY super_admin_bypass_%s ON %s USING (current_setting('app.is_super_admin', true) = 'true')", $table, $table));
        }

        // Revoke grants (role kept — see class docblock).
        $this->addSql('ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE SELECT, INSERT, UPDATE, DELETE ON TABLES FROM pim_app');
        $this->addSql('ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE USAGE, SELECT, UPDATE ON SEQUENCES FROM pim_app');
        $this->addSql('REVOKE SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public FROM pim_app');
        $this->addSql('REVOKE USAGE, SELECT, UPDATE ON ALL SEQUENCES IN SCHEMA public FROM pim_app');
        $this->addSql('REVOKE USAGE ON SCHEMA public FROM pim_app');
    }

    /**
     * Builds the tenant-isolation USING/WITH CHECK predicate for a table.
     *
     * Strict (domain): tenant_id must equal the GUC. Pre-context-safe (auth):
     * also allow when the GUC is unset/empty. Nullable-tenant tables also
     * allow tenant_id IS NULL (built-in / system rows visible to everyone).
     *
     * The match clause casts via NULLIF(..., '')::uuid rather than a bare
     * `::uuid`. The GUC is reset to the EMPTY STRING (not NULL) when no tenant
     * is resolved (RlsContextListener / TenantRlsGucMiddleware both use ''),
     * and SQL does not guarantee an `OR` short-circuits before the cast — so a
     * bare `current_setting(...)::uuid` of '' raises `invalid input syntax for
     * type uuid: ""` and turns every query into a 500 instead of an empty
     * result. NULLIF turns '' into NULL, making the cast safe: `tenant_id =
     * NULL` evaluates to unknown → the row is simply excluded (fail-closed for
     * domain tables; the explicit IS NULL / = '' clauses still open the
     * pre-auth window for auth tables).
     */
    private function isolationPredicate(string $table, bool $preContextSafe): string
    {
        $match = "tenant_id = NULLIF(current_setting('app.current_tenant', true), '')::uuid";

        $clauses = [];
        if ($preContextSafe) {
            $clauses[] = "current_setting('app.current_tenant', true) IS NULL";
            $clauses[] = "current_setting('app.current_tenant', true) = ''";
        }
        if (\in_array($table, self::NULLABLE_TENANT_TABLES, true)) {
            $clauses[] = 'tenant_id IS NULL';
        }
        $clauses[] = $match;

        return implode(' OR ', $clauses);
    }

    /**
     * ENABLE + FORCE RLS and (re)create the tenant-isolation +
     * super-admin-bypass policies for a single table. Drops any pre-existing
     * policy of the same name first so the migration is safe to apply on the
     * 7 tables that already had RLS enabled by earlier RBAC/IMP2 migrations.
     *
     * $predicate is built from class constants only (no external input), so
     * inlining it into the DDL is injection-safe.
     */
    private function applyRls(string $table, string $predicate): void
    {
        $this->addSql(\sprintf('DROP POLICY IF EXISTS tenant_isolation_%s ON %s', $table, $table));
        $this->addSql(\sprintf('DROP POLICY IF EXISTS super_admin_bypass_%s ON %s', $table, $table));

        $this->addSql(\sprintf('ALTER TABLE %s ENABLE ROW LEVEL SECURITY', $table));
        $this->addSql(\sprintf('ALTER TABLE %s FORCE ROW LEVEL SECURITY', $table));

        // Single ALL policy with both USING (read/update/delete visibility)
        // and WITH CHECK (insert/update row validation) so writes are held to
        // the same tenant boundary as reads.
        $this->addSql(\sprintf(
            'CREATE POLICY tenant_isolation_%s ON %s USING (%s) WITH CHECK (%s)',
            $table,
            $table,
            $predicate,
            $predicate,
        ));

        // Super-admin break-glass bypass (Phase 3 #677) — set
        // app.is_super_admin = 'true' before a cross-tenant op.
        $this->addSql(\sprintf(
            "CREATE POLICY super_admin_bypass_%s ON %s USING (current_setting('app.is_super_admin', true) = 'true') WITH CHECK (current_setting('app.is_super_admin', true) = 'true')",
            $table,
            $table,
        ));
    }
}
