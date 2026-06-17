<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * AUD-027 / W1-2 — reproduces the GUC name drift on the `refresh_tokens` RLS
 * policies and proves the fix migration ({@see Version20260617000000}).
 *
 * The application sets exactly one tenant GUC: `app.current_tenant`
 * (RlsContextListener on HTTP, TenantRlsGucMiddleware in workers). The legacy
 * `refresh_tokens` policies created by Version20260428195217 instead read
 * `pim.current_tenant_id`, which the code never sets. Under FORCE ROW LEVEL
 * SECURITY (the AUD-002 / W1-1 hardening) that mismatch denies every row →
 * the refresh-token login flow breaks for every tenant.
 *
 * The test schema is built from ORM metadata via Foundry (reset mode: schema),
 * so the migration-created policies do NOT exist here (pg_policies is empty for
 * the table at boot). Both tests therefore materialise the policy DDL on a real
 * `refresh_tokens` table — one from hand-written predicates (mechanism), one
 * from the actual migration SQL (fix binding).
 *
 * No production FORCE RLS is enabled: the visibility test creates and tears
 * down FORCE + a probe role entirely within itself.
 */
final class RefreshTokenRlsGucDriftTest extends KernelTestCase
{
    private const string PROBE_ROLE = 'pim_rls_guc_probe';
    private const string FIX_MIGRATION = 'Version20260617000000';

    /**
     * Mechanism: under FORCE RLS, row visibility on `refresh_tokens` follows
     * whichever GUC the policy reads. The legacy `pim.current_tenant_id` policy
     * hides the row even though the app set `app.current_tenant` (the drift);
     * the canonical `app.current_tenant` policy makes it visible (the fix).
     *
     * Asserted as a single flip — only the GUC name changes between the two
     * halves; the row, tenant, FORCE flag and reading role are identical.
     */
    #[Test]
    public function refreshTokensVisibilityFollowsTheAppCurrentTenantGuc(): void
    {
        self::bootKernel();
        $connection = $this->connection();

        $tenantId = Uuid::v7()->toRfc4122();
        $tokenId = Uuid::v7()->toRfc4122();

        $this->seedRefreshTokenRow($connection, $tokenId, $tenantId);
        $this->setUpRlsProbe($connection);

        try {
            // The application sets ONLY this GUC for the tenant boundary.
            $connection->executeStatement(
                "SELECT set_config('app.current_tenant', :t, false)",
                ['t' => $tenantId],
            );

            // ── RED: legacy policy reads pim.current_tenant_id (never set). ──
            $this->applyHandWrittenPolicy($connection, 'pim.current_tenant_id');
            self::assertSame(
                0,
                $this->countVisibleAsProbe($connection),
                'Drift reproduction: under the legacy pim.current_tenant_id policy and FORCE RLS, '
                .'the tenant row is invisible even though the app set app.current_tenant.',
            );

            // ── GREEN: canonical policy reads app.current_tenant (the fix). ──
            $this->applyHandWrittenPolicy($connection, 'app.current_tenant');
            self::assertSame(
                1,
                $this->countVisibleAsProbe($connection),
                'After unifying the policy on app.current_tenant, the tenant row is visible under FORCE RLS.',
            );
        } finally {
            $this->tearDownRlsProbe($connection, $tokenId);
        }
    }

    /**
     * Fix binding: apply the actual {@see Version20260617000000} SQL on the
     * connection, then scan pg_policies and fail if ANY `refresh_tokens` policy
     * still references `pim.current_tenant_id`. After the migration every
     * policy must read `app.current_tenant` — the contract the rest of the app
     * already obeys.
     *
     * This binds the test to the shipped migration (not a hand-written copy):
     * before the migration file existed the createVersion() call failed; with
     * a regressed migration the pim.current_tenant_id assertion fails.
     */
    #[Test]
    public function fixMigrationLeavesNoPolicyOnTheLegacyGuc(): void
    {
        self::bootKernel();
        $connection = $this->connection();

        // Start from the legacy state the migration is meant to repair, so the
        // assertion proves the migration *changed* it (not that it was already
        // clean in the schema-built test DB).
        $this->applyHandWrittenPolicy($connection, 'pim.current_tenant_id');

        try {
            self::assertGreaterThan(
                0,
                $this->countRefreshTokenPoliciesOnGuc($connection, 'pim.current_tenant_id'),
                'precondition: the legacy-GUC policies are in place before the migration runs',
            );

            $this->runFixMigrationSql($connection);

            self::assertSame(
                0,
                $this->countRefreshTokenPoliciesOnGuc($connection, 'pim.current_tenant_id'),
                'no refresh_tokens policy may read the legacy pim.current_tenant_id GUC after the fix migration',
            );
            self::assertSame(
                4,
                $this->countRefreshTokenPoliciesOnGuc($connection, 'app.current_tenant'),
                'all four SELECT/INSERT/UPDATE/DELETE refresh_tokens policies must read app.current_tenant',
            );
        } finally {
            foreach (['select', 'insert', 'update', 'delete'] as $cmd) {
                $connection->executeStatement(\sprintf('DROP POLICY IF EXISTS tenant_isolation_%s ON refresh_tokens', $cmd));
            }
        }
    }

    private function seedRefreshTokenRow(Connection $connection, string $tokenId, string $tenantId): void
    {
        // RefreshToken is not TenantScoped and its rows are written by raw
        // service code, so insert directly. token_hash is UNIQUE; a random
        // value keeps parallel test runs from colliding.
        $connection->executeStatement(
            <<<'SQL'
                INSERT INTO refresh_tokens
                    (id, tenant_id, user_id, family_id, token_hash, issued_at, expires_at)
                VALUES
                    (:id, :tenant, :user, :family, :hash, NOW(), NOW() + INTERVAL '1 day')
                SQL,
            [
                'id' => $tokenId,
                'tenant' => $tenantId,
                'user' => Uuid::v7()->toRfc4122(),
                'family' => Uuid::v7()->toRfc4122(),
                'hash' => bin2hex(random_bytes(32)),
            ],
        );
    }

    private function setUpRlsProbe(Connection $connection): void
    {
        // A NOBYPASSRLS role so FORCE RLS is actually enforced on reads —
        // unlike the default superuser test connection. The role name is a
        // class constant (no external input), so inlining it is injection-safe.
        $role = self::PROBE_ROLE;
        $connection->executeStatement(
            <<<SQL
                DO \$\$ BEGIN
                    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = '{$role}') THEN
                        CREATE ROLE {$role} NOLOGIN NOBYPASSRLS;
                    END IF;
                END \$\$
                SQL,
        );
        $connection->executeStatement(\sprintf('GRANT SELECT ON refresh_tokens TO %s', self::PROBE_ROLE));

        $connection->executeStatement('ALTER TABLE refresh_tokens ENABLE ROW LEVEL SECURITY');
        $connection->executeStatement('ALTER TABLE refresh_tokens FORCE ROW LEVEL SECURITY');
    }

    /**
     * Recreates the four tenant-isolation policies against $guc, mirroring the
     * migration's DDL (the SELECT policy is the one exercised by the read).
     */
    private function applyHandWrittenPolicy(Connection $connection, string $guc): void
    {
        foreach (['select', 'insert', 'update', 'delete'] as $cmd) {
            $connection->executeStatement(\sprintf('DROP POLICY IF EXISTS tenant_isolation_%s ON refresh_tokens', $cmd));
        }

        $predicate = \sprintf("tenant_id = current_setting('%s', true)::uuid", $guc);
        $connection->executeStatement(\sprintf('CREATE POLICY tenant_isolation_select ON refresh_tokens FOR SELECT USING (%s)', $predicate));
        $connection->executeStatement(\sprintf('CREATE POLICY tenant_isolation_insert ON refresh_tokens FOR INSERT WITH CHECK (%s)', $predicate));
        $connection->executeStatement(\sprintf('CREATE POLICY tenant_isolation_update ON refresh_tokens FOR UPDATE USING (%1$s) WITH CHECK (%1$s)', $predicate));
        $connection->executeStatement(\sprintf('CREATE POLICY tenant_isolation_delete ON refresh_tokens FOR DELETE USING (%s)', $predicate));
    }

    /**
     * Counts rows visible through RLS as the NOBYPASSRLS probe role. The GUC
     * (a session-local value set with is_local=false) survives the SET ROLE /
     * RESET ROLE on the same connection.
     */
    private function countVisibleAsProbe(Connection $connection): int
    {
        $connection->executeStatement(\sprintf('SET ROLE %s', self::PROBE_ROLE));
        try {
            $count = $connection->fetchOne('SELECT COUNT(*) FROM refresh_tokens');

            return (int) (\is_scalar($count) ? $count : -1);
        } finally {
            $connection->executeStatement('RESET ROLE');
        }
    }

    /**
     * Loads the fix migration via the same factory the migrator uses and
     * executes its `up()` SQL verbatim (the migration binds no parameters).
     */
    private function runFixMigrationSql(Connection $connection): void
    {
        // Migration classes are intentionally NOT autoloaded (see
        // config/packages/doctrine_migrations.yaml). require_once defines the
        // class; the MigrationFactory wires the connection + logger.
        require_once \dirname(__DIR__, 3).'/migrations/'.self::FIX_MIGRATION.'.php';
        $migration = self::getContainer()
            ->get('doctrine.migrations.dependency_factory')
            ->getMigrationFactory()
            ->createVersion('DoctrineMigrations\\'.self::FIX_MIGRATION);
        $migration->up(new Schema());

        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement());
        }
    }

    private function countRefreshTokenPoliciesOnGuc(Connection $connection, string $guc): int
    {
        $count = $connection->fetchOne(
            <<<'SQL'
                SELECT COUNT(*) FROM pg_policies
                WHERE schemaname = 'public'
                  AND tablename = 'refresh_tokens'
                  AND (COALESCE(qual, '') LIKE :needle OR COALESCE(with_check, '') LIKE :needle)
                SQL,
            ['needle' => '%'.$guc.'%'],
        );

        return (int) (\is_scalar($count) ? $count : -1);
    }

    private function tearDownRlsProbe(Connection $connection, string $tokenId): void
    {
        // Best-effort cleanup — make sure FORCE RLS never leaks past the test.
        $connection->executeStatement('RESET ROLE');
        foreach (['select', 'insert', 'update', 'delete'] as $cmd) {
            $connection->executeStatement(\sprintf('DROP POLICY IF EXISTS tenant_isolation_%s ON refresh_tokens', $cmd));
        }
        $connection->executeStatement('ALTER TABLE refresh_tokens NO FORCE ROW LEVEL SECURITY');
        $connection->executeStatement('ALTER TABLE refresh_tokens DISABLE ROW LEVEL SECURITY');
        $connection->executeStatement("SELECT set_config('app.current_tenant', '', false)");
        $connection->executeStatement(\sprintf('REVOKE SELECT ON refresh_tokens FROM %s', self::PROBE_ROLE));
        $connection->executeStatement(\sprintf('DROP ROLE IF EXISTS %s', self::PROBE_ROLE));
        $connection->executeStatement('DELETE FROM refresh_tokens WHERE id = :id', ['id' => $tokenId]);
    }

    private function connection(): Connection
    {
        // The container PHPStan extension already types this service id as
        // Connection (an assert here is flagged always-true), so return as-is.
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        return $connection;
    }
}
