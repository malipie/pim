<?php

declare(strict_types=1);

namespace App\Maintenance;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Audit every public-schema table for `tenant_id` coverage.
 *
 * What it checks:
 *   - Domain tables (everything outside the {@see INFRA_TABLES} allowlist)
 *     must have a `tenant_id` column.
 *   - That column should be `NOT NULL` unless the table is on the
 *     {@see NULLABLE_TENANT_TABLES} list (currently only `roles` — global
 *     built-in roles persist with `tenant_id IS NULL`).
 *   - That column should carry an index — perf concern, not correctness.
 *
 * Exit codes:
 *   - 0: clean (no FAILs).
 *   - 1: at least one FAIL detected (missing tenant_id, or unexpected
 *     nullable, or unindexed). The CLI prints the table-by-table report so
 *     the operator knows exactly which row to fix.
 *
 * Idempotent and read-only — safe to run on production.
 *
 * See `docs/multi-tenancy.md` for context. Future entities (`Object`,
 * `Channel`, `Asset`) added in epic 0.3 should land green on first audit;
 * if a new domain table forgets `tenant_id`, this command catches it.
 */
#[AsCommand(
    name: 'pim:tenant:audit',
    description: 'Audit every public-schema domain table for tenant_id coverage.'
)]
final class TenantAuditCommand extends Command
{
    private const string SEVERITY_OK = 'OK';
    private const string SEVERITY_WARN = 'WARN';
    private const string SEVERITY_FAIL = 'FAIL';

    /**
     * Tables that are infrastructure or unscoped by design — they should NOT
     * be flagged when they lack `tenant_id`. Anything outside this list is
     * treated as domain and must carry the column.
     *
     * Notes:
     *   - `tenants`: the tenant entity itself; not scoped.
     *   - `permissions`: catalog of (resource, action) pairs, intentionally
     *     global (#27 / RbacMatrix).
     *   - `role_permissions`, `user_roles`: M2M junctions; tenant scope
     *     follows the parent rows.
     *   - `messenger_messages`: Symfony Messenger queue, infra.
     *   - `doctrine_migration_versions`: Doctrine bookkeeping.
     *
     * @var list<string>
     */
    private const array INFRA_TABLES = [
        'tenants',
        'permissions',
        'role_permissions',
        'user_roles',
        'messenger_messages',
        'doctrine_migration_versions',
    ];

    /**
     * Domain tables where `tenant_id` is legitimately nullable (global rows
     * carry NULL). Audit downgrades the nullability check to OK for these.
     *
     * @var list<string>
     */
    private const array NULLABLE_TENANT_TABLES = [
        'roles',
    ];

    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $tables = $this->fetchPublicTables();
        $rows = [];
        $failures = 0;

        foreach ($tables as $table) {
            if (\in_array($table, self::INFRA_TABLES, true)) {
                $rows[] = [$table, 'infra (skipped)', '—', '—', self::SEVERITY_OK];
                continue;
            }

            $column = $this->fetchTenantIdColumn($table);
            if (null === $column) {
                $rows[] = [$table, 'MISSING', '—', '—', self::SEVERITY_FAIL];
                ++$failures;
                continue;
            }

            $nullable = 'YES' === $column['is_nullable'];
            $nullableSeverity = self::SEVERITY_OK;
            if ($nullable && !\in_array($table, self::NULLABLE_TENANT_TABLES, true)) {
                $nullableSeverity = self::SEVERITY_FAIL;
                ++$failures;
            }

            $hasIndex = $this->columnHasIndex($table, 'tenant_id');
            $indexSeverity = $hasIndex ? self::SEVERITY_OK : self::SEVERITY_WARN;

            $worst = self::SEVERITY_OK;
            foreach ([$nullableSeverity, $indexSeverity] as $candidate) {
                if (self::SEVERITY_FAIL === $candidate) {
                    $worst = self::SEVERITY_FAIL;
                    break;
                }
                if (self::SEVERITY_WARN === $candidate && self::SEVERITY_OK === $worst) {
                    $worst = self::SEVERITY_WARN;
                }
            }

            $rows[] = [
                $table,
                'present',
                $nullable ? 'NULL OK' : 'NOT NULL',
                $hasIndex ? 'indexed' : 'no index',
                $worst,
            ];
        }

        $io->table(['table', 'tenant_id', 'nullability', 'index', 'severity'], $rows);

        if ($failures > 0) {
            $io->error(\sprintf('Audit found %d failure(s).', $failures));

            return Command::FAILURE;
        }

        $io->success('All domain tables carry tenant_id.');

        return Command::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function fetchPublicTables(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE' ORDER BY table_name",
        );
        $names = [];
        foreach ($rows as $row) {
            $name = $row['table_name'] ?? null;
            if (\is_string($name)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @return array{is_nullable: string}|null
     */
    private function fetchTenantIdColumn(string $table): ?array
    {
        $row = $this->connection->fetchAssociative(
            "SELECT is_nullable FROM information_schema.columns WHERE table_schema = 'public' AND table_name = :table AND column_name = 'tenant_id'",
            ['table' => $table],
        );
        if (false === $row) {
            return null;
        }

        $nullable = $row['is_nullable'] ?? null;
        if (!\is_string($nullable)) {
            return null;
        }

        return ['is_nullable' => $nullable];
    }

    private function columnHasIndex(string $table, string $column): bool
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
                    SELECT 1
                    FROM pg_index i
                    JOIN pg_class c ON c.oid = i.indrelid
                    JOIN pg_attribute a ON a.attrelid = c.oid AND a.attnum = ANY(i.indkey)
                    WHERE c.relname = :table AND a.attname = :column
                    LIMIT 1
                SQL,
            ['table' => $table, 'column' => $column],
        );

        return false !== $row;
    }
}
