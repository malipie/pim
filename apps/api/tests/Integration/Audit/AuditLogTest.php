<?php

declare(strict_types=1);

namespace App\Tests\Integration\Audit;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Smoke coverage for the DH Auditor audit-log surface (#99 / 0.11.4).
 *
 * What this test pins:
 *   - the per-entity audit tables come up with the schema the bundle
 *     contracts (`type`, `object_id`, `transaction_hash`, `diffs`,
 *     `blame_id`, `created_at`, …);
 *   - the audit-log cleanup CLI (`pim:audit:cleanup`) lists every
 *     `<entity>_audit` table by introspecting `pg_tables`.
 *
 * What this test deliberately does NOT do: insert a row through the
 * Doctrine ORM and read the audit row back. The DAMADoctrineTestBundle
 * wraps every PHPUnit test in a single rolled-back transaction so the
 * audit subscriber's INSERT and the assertion's SELECT see different
 * states. End-to-end audit-write coverage runs through manual smoke
 * + production observability — see `lessons.md` (0.11.4 entry) for
 * the workaround when this needs CI coverage in a follow-up.
 */
final class AuditLogTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private const array EXPECTED_AUDIT_TABLES = [
        // Catalog schema — see dh_auditor.yaml note on why catalog DATA
        // tables (objects, object_values, object_associations) are
        // intentionally absent here.
        'object_types_audit',
        'attributes_audit',
        'attribute_groups_audit',
        'attribute_options_audit',
        'association_types_audit',
        'channels_audit',
        'assets_audit',
        'users_audit',
        'roles_audit',
        'permissions_audit',
        'tenants_audit',
        'api_profiles_audit',
        'api_keys_audit',
    ];

    #[Test]
    public function everyConfiguredEntityHasAnAuditTable(): void
    {
        $rows = $this->connection()->fetchAllAssociative(<<<'SQL'
                SELECT tablename
                FROM pg_catalog.pg_tables
                WHERE schemaname = ANY (current_schemas(false))
                  AND tablename LIKE '%_audit'
                ORDER BY tablename
            SQL);

        $present = array_column($rows, 'tablename');

        foreach (self::EXPECTED_AUDIT_TABLES as $expected) {
            self::assertContains(
                $expected,
                $present,
                \sprintf('Audit table "%s" missing — bundle config drift?', $expected),
            );
        }
    }

    #[Test]
    public function auditTableCarriesTheBundleSchema(): void
    {
        $columns = $this->connection()->fetchAllAssociative(<<<'SQL'
                SELECT column_name
                FROM information_schema.columns
                WHERE table_schema = ANY (current_schemas(false))
                  AND table_name = 'attributes_audit'
            SQL);
        $names = array_column($columns, 'column_name');

        foreach (
            ['id', 'type', 'object_id', 'discriminator', 'transaction_hash',
                'diffs', 'blame_id', 'blame_user', 'blame_user_fqdn',
                'blame_user_firewall', 'ip', 'created_at'] as $expected
        ) {
            self::assertContains(
                $expected,
                $names,
                \sprintf('attributes_audit must carry the bundle column "%s".', $expected),
            );
        }
    }

    #[Test]
    public function cleanupCommandIntrospectsAuditTables(): void
    {
        $kernel = self::bootKernel();
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application($kernel);
        $command = $application->find('pim:audit:cleanup');
        $tester = new \Symfony\Component\Console\Tester\CommandTester($command);

        $tester->execute(['--dry-run' => true]);

        $output = $tester->getDisplay();
        self::assertStringContainsString('attributes_audit', $output);
        self::assertStringContainsString('users_audit', $output);
        self::assertStringContainsString('Would prune 0 rows', $output);
    }

    private function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }

    private function connection(): Connection
    {
        return $this->em()->getConnection();
    }
}
