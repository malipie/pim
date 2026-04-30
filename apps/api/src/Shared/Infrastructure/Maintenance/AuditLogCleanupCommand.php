<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Maintenance;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Prunes DH Auditor audit-log tables past the retention horizon
 * (#99 / 0.11.4 — default 365 days).
 *
 * Operators run this from cron daily; the bundle does not prune by
 * itself. Per-table DELETE keeps locks scoped — one slow table does
 * not stall the others. Dry-run reports row counts without touching
 * data so the cron can stay loud.
 */
#[AsCommand(
    name: 'pim:audit:cleanup',
    description: 'Prune audit-log tables past the retention horizon. Default: 365 days.',
)]
final class AuditLogCleanupCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'older-than',
                null,
                InputOption::VALUE_REQUIRED,
                'Retention window — supports `Nd` / `Nw` / `Nm` (days, weeks, months).',
                '365d',
            )
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report row counts without deleting.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var string $window */
        $window = $input->getOption('older-than');
        /** @var bool $dryRun */
        $dryRun = $input->getOption('dry-run');

        $interval = $this->parseWindow($window);
        if (null === $interval) {
            $io->error(\sprintf('Invalid --older-than value "%s" (use forms like 365d, 12w, 6m).', $window));

            return Command::INVALID;
        }

        $tables = $this->listAuditTables();
        if ([] === $tables) {
            $io->success('No audit-log tables found — nothing to prune.');

            return Command::SUCCESS;
        }

        $io->section(\sprintf('Pruning audit logs older than %s (%d tables)', $window, \count($tables)));

        $totalDeleted = 0;
        foreach ($tables as $table) {
            $count = $dryRun
                ? $this->countOlderThan($table, $interval)
                : $this->deleteOlderThan($table, $interval);
            $totalDeleted += $count;
            $io->text(\sprintf('  %s: %d rows %s', $table, $count, $dryRun ? '(dry-run)' : 'pruned'));
        }

        $io->success(\sprintf(
            '%s%d rows %s across %d tables.',
            $dryRun ? 'Would prune ' : 'Pruned ',
            $totalDeleted,
            $dryRun ? '(dry-run)' : '',
            \count($tables),
        ));

        return Command::SUCCESS;
    }

    private function parseWindow(string $value): ?string
    {
        if (1 !== preg_match('/^(\d+)([dwm])$/', strtolower($value), $matches)) {
            return null;
        }
        $n = (int) $matches[1];
        if ($n <= 0) {
            return null;
        }

        return match ($matches[2]) {
            'd' => $n.' days',
            'w' => ($n * 7).' days',
            'm' => $n.' months',
        };
    }

    /**
     * @return list<string>
     */
    private function listAuditTables(): array
    {
        $rows = $this->connection->fetchAllAssociative(<<<'SQL'
                SELECT tablename
                FROM pg_catalog.pg_tables
                WHERE schemaname = ANY (current_schemas(false))
                  AND tablename LIKE '%_audit'
                ORDER BY tablename
            SQL);

        $tables = [];
        foreach ($rows as $row) {
            $name = $row['tablename'] ?? null;
            if (\is_string($name) && '' !== $name) {
                $tables[] = $name;
            }
        }

        return $tables;
    }

    private function countOlderThan(string $table, string $interval): int
    {
        // Identifier comes from `pg_tables` introspection — already a
        // safe table name. Quote single-identifier per the Doctrine 4 API.
        $sql = \sprintf(
            'SELECT COUNT(*) AS c FROM %s WHERE created_at < (NOW() - INTERVAL %s)',
            $this->connection->quoteSingleIdentifier($table),
            $this->connection->quote($interval),
        );
        $result = $this->connection->fetchOne($sql);

        return is_numeric($result) ? (int) $result : 0;
    }

    private function deleteOlderThan(string $table, string $interval): int
    {
        $sql = \sprintf(
            'DELETE FROM %s WHERE created_at < (NOW() - INTERVAL %s)',
            $this->connection->quoteSingleIdentifier($table),
            $this->connection->quote($interval),
        );

        return (int) $this->connection->executeStatement($sql);
    }
}
