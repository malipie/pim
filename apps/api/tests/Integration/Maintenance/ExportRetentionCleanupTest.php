<?php

declare(strict_types=1);

namespace App\Tests\Integration\Maintenance;

use App\Shared\Domain\Tenant;
use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * AUD-050 (W2-11) — proves export retention enforcement (GDPR / RODO): free-tier
 * exports (full data / PII) past the retention window get erased — file + DB row —
 * while fresh free-tier exports and ALL paid-tier exports survive forever.
 *
 * FAILING-FIRST: before the fix `pim:exports:cleanup` does not exist, so the
 * CommandTester construction throws CommandNotFoundException and the test is RED.
 * After the fix the command iterates tenants under TenantContext, deletes the
 * stale free-tier session rows + their MinIO objects, and leaves the rest.
 *
 * OPERATOR-DATA SAFETY: throwaway tenants with random uuids; schema reset per
 * test (Foundry ResetDatabase). Never touches the real demo / acme tenants.
 */
final class ExportRetentionCleanupTest extends KernelTestCase
{
    use ResetDatabase;

    #[Test]
    public function deletesStaleFreeTierExportsAndKeepsFreshAndPaid(): void
    {
        $kernel = self::bootKernel();
        $connection = $this->connection();
        $exports = $this->exportsStorage();

        $free = Uuid::v7();
        $paid = Uuid::v7();
        $this->seedTenant($connection, $free, 'free', Tenant::PLAN_STARTER);
        $this->seedTenant($connection, $paid, 'paid', Tenant::PLAN_PRO);

        $freeUser = $this->seedUser($connection, $free);
        $paidUser = $this->seedUser($connection, $paid);

        // Free tenant: one stale export (8 days old, default window 7) + one fresh.
        $staleFree = $this->seedSession($connection, $exports, $free, $freeUser, '-8 days');
        $freshFree = $this->seedSession($connection, $exports, $free, $freeUser, '-1 days');
        // Paid tenant: one equally-stale export — must be kept (forever retention).
        $stalePaid = $this->seedSession($connection, $exports, $paid, $paidUser, '-8 days');

        // Sanity: every session + file is present before the cleanup.
        self::assertTrue($exports->fileExists($this->path($free, $staleFree)));
        self::assertTrue($exports->fileExists($this->path($free, $freshFree)));
        self::assertTrue($exports->fileExists($this->path($paid, $stalePaid)));

        $tester = $this->commandTester($kernel);
        $exit = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit, $tester->getDisplay());

        // Stale free-tier export: row + file gone.
        self::assertSame(0, $this->rowExists($connection, $staleFree), 'Stale free-tier session row must be deleted.');
        self::assertFalse($exports->fileExists($this->path($free, $staleFree)), 'Stale free-tier file must be deleted.');

        // Fresh free-tier export: untouched.
        self::assertSame(1, $this->rowExists($connection, $freshFree), 'Fresh free-tier session must survive.');
        self::assertTrue($exports->fileExists($this->path($free, $freshFree)), 'Fresh free-tier file must survive.');

        // Stale paid-tier export: untouched (forever retention).
        self::assertSame(1, $this->rowExists($connection, $stalePaid), 'Paid-tier session must survive forever.');
        self::assertTrue($exports->fileExists($this->path($paid, $stalePaid)), 'Paid-tier file must survive forever.');
    }

    #[Test]
    public function dryRunDeletesNothing(): void
    {
        $kernel = self::bootKernel();
        $connection = $this->connection();
        $exports = $this->exportsStorage();

        $free = Uuid::v7();
        $this->seedTenant($connection, $free, 'free-dry', Tenant::PLAN_STARTER);
        $freeUser = $this->seedUser($connection, $free);
        $staleFree = $this->seedSession($connection, $exports, $free, $freeUser, '-30 days');

        $tester = $this->commandTester($kernel);
        $exit = $tester->execute(['--dry-run' => true]);

        self::assertSame(Command::SUCCESS, $exit, $tester->getDisplay());
        self::assertSame(1, $this->rowExists($connection, $staleFree), 'Dry-run must keep the row.');
        self::assertTrue($exports->fileExists($this->path($free, $staleFree)), 'Dry-run must keep the file.');
    }

    private function seedTenant(Connection $connection, Uuid $id, string $codePrefix, string $plan): void
    {
        $suffix = bin2hex(random_bytes(4));
        $connection->executeStatement(
            'INSERT INTO tenants (id, code, name, plan, created_at) VALUES (:id, :code, :name, :plan, NOW())',
            ['id' => $id->toRfc4122(), 'code' => $codePrefix.'-'.$suffix, 'name' => $codePrefix, 'plan' => $plan],
        );
    }

    private function seedUser(Connection $connection, Uuid $tenant): Uuid
    {
        $userId = Uuid::v7();
        $suffix = bin2hex(random_bytes(4));
        $connection->executeStatement(
            <<<'SQL'
                INSERT INTO users (id, email, password, roles, status, totp_backup_codes,
                                   created_at, password_change_required, tenant_id)
                VALUES (:id, :email, 'x', '[]'::json, 'active', '[]'::jsonb, NOW(), false, :t)
                SQL,
            ['id' => $userId->toRfc4122(), 'email' => 'user-'.$suffix.'@example.test', 't' => $tenant->toRfc4122()],
        );

        return $userId;
    }

    /**
     * Seeds a done export_session with a file at `<tenant>/<session>.xlsx` and
     * `started_at` shifted by $when. Returns the session id.
     */
    private function seedSession(
        Connection $connection,
        FilesystemOperator $exports,
        Uuid $tenant,
        Uuid $user,
        string $when,
    ): Uuid {
        $sessionId = Uuid::v7();
        $path = $this->path($tenant, $sessionId);
        $connection->executeStatement(
            <<<'SQL'
                INSERT INTO export_sessions
                    (id, tenant_id, user_id, source, format, target_scope, entity_type,
                     selected_columns, include_variants, target_count, success_count,
                     status, started_at, file_path)
                VALUES
                    (:id, :t, :uid, 'manual', 'xlsx', 'all', 'product',
                     '[]'::jsonb, false, 1, 1,
                     'done', NOW() + (:when)::interval, :path)
                SQL,
            [
                'id' => $sessionId->toRfc4122(),
                't' => $tenant->toRfc4122(),
                'uid' => $user->toRfc4122(),
                'when' => $when,
                'path' => $path,
            ],
        );
        $exports->write($path, 'xlsx-bytes');

        return $sessionId;
    }

    private function path(Uuid $tenant, Uuid $session): string
    {
        return $tenant->toRfc4122().'/'.$session->toRfc4122().'.xlsx';
    }

    private function rowExists(Connection $connection, Uuid $session): int
    {
        $value = $connection->fetchOne(
            'SELECT COUNT(*) FROM export_sessions WHERE id = :id',
            ['id' => $session->toRfc4122()],
        );

        return \is_numeric($value) ? (int) $value : 0;
    }

    private function commandTester(object $kernel): CommandTester
    {
        \assert($kernel instanceof \Symfony\Component\HttpKernel\KernelInterface);
        $application = new Application($kernel);

        return new CommandTester($application->find('pim:exports:cleanup'));
    }

    private function connection(): Connection
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get('doctrine.dbal.default_connection');

        return $connection;
    }

    private function exportsStorage(): FilesystemOperator
    {
        // The container resolves `exports.storage` to a concrete Filesystem
        // (a FilesystemOperator) — return it directly, no narrowing needed.
        return self::getContainer()->get('exports.storage');
    }
}
