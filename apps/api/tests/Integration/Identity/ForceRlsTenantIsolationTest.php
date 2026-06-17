<?php

declare(strict_types=1);

namespace App\Tests\Integration\Identity;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * AUD-002 / W1-1 — proves Postgres FORCE ROW LEVEL SECURITY is a real
 * cross-tenant wall once the application connects as a NON-owner,
 * NOBYPASSRLS role.
 *
 * Why this test exists: before W1-1 the app connected as `pim`
 * (superuser + BYPASSRLS + owner of every table), so RLS was inert in
 * runtime — a TenantFilter bug leaked rows with no backstop. The fix
 * introduces the `pim_app` runtime role and FORCE RLS + tenant-isolation
 * policies on every tenant-scoped table. This test reproduces both states
 * on a representative DOMAIN table (`channels`) on the same connection:
 *
 *   RED   — as a NOBYPASSRLS role with RLS NOT forced (the pre-W1-1 world,
 *           modelled by an owner/bypass reader), tenant B's row is visible
 *           while scoped to tenant A: the isolation does not hold at the DB
 *           layer.
 *   GREEN — after applying the SHIPPED migration's FORCE RLS + policy DDL
 *           for `channels`, the same NOBYPASSRLS reader scoped to tenant A
 *           (app.current_tenant = A) sees 0 rows of tenant B and exactly
 *           its own row.
 *
 * Binding to the shipped migration (not a hand-written copy) means a
 * regression that drops `channels` from the policy sweep, or weakens the
 * predicate, fails here.
 *
 * The test DB schema is built from ORM metadata via Foundry (reset mode:
 * schema), so the migration-created policies do NOT exist at boot — the test
 * materialises them itself and tears everything down in a finally so FORCE
 * RLS never leaks past the test (which would break every other test sharing
 * the DB).
 */
final class ForceRlsTenantIsolationTest extends KernelTestCase
{
    private const string PROBE_ROLE = 'pim_force_rls_probe';
    private const string MIGRATION = 'Version20260617050000';

    /**
     * Mechanism + fix in a single flip on a domain table. Only the FORCE RLS
     * policy state changes between the two halves; the rows, tenants, GUC and
     * reading role are identical.
     */
    #[Test]
    public function channelsAreInvisibleAcrossTenantsUnderForceRls(): void
    {
        self::bootKernel();
        $connection = $this->connection();

        $tenantA = Uuid::v7()->toRfc4122();
        $tenantB = Uuid::v7()->toRfc4122();
        $channelA = Uuid::v7()->toRfc4122();
        $channelB = Uuid::v7()->toRfc4122();

        // Random code suffixes keep parallel test runs (ParaTest) from
        // colliding on the unique (tenant_id, code) constraints.
        $suffix = bin2hex(random_bytes(4));
        $this->seedTenant($connection, $tenantA, 'rls-a-'.$suffix);
        $this->seedTenant($connection, $tenantB, 'rls-b-'.$suffix);
        $this->seedChannel($connection, $channelA, $tenantA, 'shopify-a-'.$suffix);
        $this->seedChannel($connection, $channelB, $tenantB, 'shopify-b-'.$suffix);
        $this->setUpProbeRole($connection);

        try {
            // Scope the session to tenant A. The application sets ONLY this GUC.
            $connection->executeStatement(
                "SELECT set_config('app.current_tenant', :t, false)",
                ['t' => $tenantA],
            );

            // ── RED: no RLS on `channels` yet (the pre-W1-1 reality — RLS inert).
            // Read as the NOBYPASSRLS probe role scoped to tenant A: it still
            // sees tenant B's row, because with no policy the DB does no
            // filtering. This proves the app-layer TenantFilter was the ONLY
            // wall before W1-1.
            self::assertSame(
                1,
                $this->countSpecificChannelAsProbe($connection, $channelB),
                'Pre-FORCE: without an RLS policy, a NOBYPASSRLS reader scoped to '
                .'tenant A still sees tenant B’s channel — no DB-layer isolation.',
            );

            // ── GREEN: apply the SHIPPED migration's FORCE RLS + policy for
            // `channels`, then read as the NOBYPASSRLS probe role scoped to A.
            $this->applyShippedChannelsPolicy($connection);

            self::assertSame(
                0,
                $this->countSpecificChannelAsProbe($connection, $channelB),
                'After FORCE RLS + tenant policy, tenant B’s channel is invisible '
                .'while scoped to tenant A.',
            );
            self::assertSame(
                1,
                $this->countSpecificChannelAsProbe($connection, $channelA),
                'Tenant A still sees its own channel under FORCE RLS.',
            );
        } finally {
            $this->cleanup($connection, [$channelA, $channelB], [$tenantA, $tenantB]);
        }
    }

    private function seedTenant(Connection $connection, string $id, string $code): void
    {
        // `channels.tenant_id` has a FK to `tenants`; seed the parent first.
        // Columns beyond the NOT NULL set carry schema defaults (plan,
        // enabled_locales, primary_locale, status).
        $connection->executeStatement(
            <<<'SQL'
                INSERT INTO tenants (id, code, name, created_at)
                VALUES (:id, :code, :name, NOW())
                SQL,
            ['id' => $id, 'code' => $code, 'name' => $code],
        );
    }

    private function seedChannel(Connection $connection, string $id, string $tenantId, string $code): void
    {
        // Insert the NOT NULL column set for `channels` (id, tenant_id, code,
        // name). Done as the owner before FORCE is applied, so no policy blocks
        // the seed.
        $connection->executeStatement(
            <<<'SQL'
                INSERT INTO channels (id, tenant_id, code, name)
                VALUES (:id, :tenant, :code, :name)
                SQL,
            [
                'id' => $id,
                'tenant' => $tenantId,
                'code' => $code,
                'name' => $code,
            ],
        );
    }

    private function setUpProbeRole(Connection $connection): void
    {
        // A NOBYPASSRLS role so FORCE RLS is actually enforced on its reads —
        // unlike the superuser/owner test connection. Name is a class constant
        // (no external input), so inlining it is injection-safe.
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
        $connection->executeStatement(\sprintf('GRANT SELECT ON channels TO %s', self::PROBE_ROLE));
    }

    /**
     * Executes the shipped migration's up() SQL and replays only the
     * statements that touch `channels` (ENABLE/FORCE RLS + the two CREATE
     * POLICY lines). Binds the test to the real migration: a regression in the
     * policy predicate or table list fails the GREEN assertions.
     */
    private function applyShippedChannelsPolicy(Connection $connection): void
    {
        require_once \dirname(__DIR__, 3).'/migrations/'.self::MIGRATION.'.php';
        $migration = self::getContainer()
            ->get('doctrine.migrations.dependency_factory')
            ->getMigrationFactory()
            ->createVersion('DoctrineMigrations\\'.self::MIGRATION);
        $migration->up(new Schema());

        foreach ($migration->getSql() as $query) {
            $statement = $query->getStatement();
            // Replay only the channels-scoped DDL (ENABLE/FORCE RLS + the two
            // CREATE/DROP POLICY lines). The role GRANTs and the other tables'
            // policies are out of scope for this focused test, and the
            // schema-wide GRANT / ALTER DEFAULT PRIVILEGES statements would
            // mutate the shared test DB beyond this test's teardown.
            if ($this->isChannelsStatement($statement)) {
                $connection->executeStatement($statement);
            }
        }
    }

    private function isChannelsStatement(string $statement): bool
    {
        // Precise match: the statement targets the `channels` table (ALTER
        // TABLE channels … / CREATE|DROP POLICY … ON channels), never another
        // table whose name merely contains the substring "channel".
        return (bool) preg_match('/\b(ALTER TABLE channels\b|POLICY \w+ ON channels\b)/', $statement);
    }

    private function countSpecificChannelAsProbe(Connection $connection, string $id): int
    {
        $connection->executeStatement(\sprintf('SET ROLE %s', self::PROBE_ROLE));
        try {
            $count = $connection->fetchOne('SELECT COUNT(*) FROM channels WHERE id = :id', ['id' => $id]);

            return (int) (\is_scalar($count) ? $count : -1);
        } finally {
            $connection->executeStatement('RESET ROLE');
        }
    }

    /**
     * @param list<string> $channelIds
     * @param list<string> $tenantIds
     */
    private function cleanup(Connection $connection, array $channelIds, array $tenantIds): void
    {
        // Best-effort cleanup — FORCE RLS must never leak past the test.
        $connection->executeStatement('RESET ROLE');
        $connection->executeStatement('DROP POLICY IF EXISTS tenant_isolation_channels ON channels');
        $connection->executeStatement('DROP POLICY IF EXISTS super_admin_bypass_channels ON channels');
        $connection->executeStatement('ALTER TABLE channels NO FORCE ROW LEVEL SECURITY');
        $connection->executeStatement('ALTER TABLE channels DISABLE ROW LEVEL SECURITY');
        $connection->executeStatement("SELECT set_config('app.current_tenant', '', false)");
        $connection->executeStatement(\sprintf('REVOKE SELECT ON channels FROM %s', self::PROBE_ROLE));
        $connection->executeStatement(\sprintf('DROP ROLE IF EXISTS %s', self::PROBE_ROLE));
        foreach ($channelIds as $id) {
            $connection->executeStatement('DELETE FROM channels WHERE id = :id', ['id' => $id]);
        }
        foreach ($tenantIds as $id) {
            $connection->executeStatement('DELETE FROM tenants WHERE id = :id', ['id' => $id]);
        }
    }

    private function connection(): Connection
    {
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        return $connection;
    }
}
