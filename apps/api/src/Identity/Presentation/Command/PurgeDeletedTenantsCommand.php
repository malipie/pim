<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Command;

use App\Identity\Application\SuperAdmin\SuperAdminContext;
use App\Shared\Domain\Repository\TenantRepositoryInterface;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

/**
 * RBAC-P5-021 (#711) — hard-deletes tenants whose soft-delete window
 * has expired (default 30 days). Designed to be invoked from cron or
 * Symfony Scheduler — daily cadence is enough since the grace period
 * is two orders of magnitude longer than the smallest cadence.
 *
 * Soft-delete semantics:
 *   - `tenants.status = 'deleted'` + `tenants.deleted_at = NOW()` set
 *     by the Super Admin operator via the API.
 *   - Recovery window is `--retention-days` (default 30).
 *   - After the window expires, this command hard-deletes the tenant
 *     row. CASCADE on FKs takes care of dependent rows (users,
 *     api_tokens, audit_logs, etc.).
 *
 * The hard delete runs inside `SuperAdminContext::runCrossTenant()` so
 * the `tenant_filter` doesn't hide deleted rows from the lookup query.
 * `--dry-run` lists the candidate rows without touching them — safer
 * default for the first deployment cycle. Operators flip the flag off
 * once they've validated the candidates are correct.
 *
 * No platform-Super-Admin uuid is needed for the audit trail — the
 * command runs from cron (no human Super Admin), so the audit listener
 * picks up the CLI marker via `userAgent`.
 */
#[AsCommand(
    name: 'pim:tenants:purge-deleted',
    description: 'Hard-deletes tenants whose soft-delete window has expired (default 30 days).',
)]
final class PurgeDeletedTenantsCommand extends Command
{
    private const int DEFAULT_RETENTION_DAYS = 30;

    public function __construct(
        private readonly Connection $connection,
        private readonly SuperAdminContext $superAdminContext,
        private readonly TenantRepositoryInterface $tenants,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'retention-days',
                null,
                InputOption::VALUE_REQUIRED,
                'Days the soft-delete must have aged before hard-delete fires.',
                (string) self::DEFAULT_RETENTION_DAYS,
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'List the candidate tenants without deleting them.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $retention = (int) $input->getOption('retention-days');
        if ($retention < 1) {
            $io->error('`--retention-days` must be a positive integer.');

            return Command::FAILURE;
        }
        $dryRun = $input->getOption('dry-run') === true;

        // Run the lookup under cross-tenant mode so the tenant_filter
        // (active by default for SELECTs) doesn't hide candidates.
        // Using a synthetic Uuid is fine here — no audit row is
        // attributed to a specific human Super Admin for the scheduled
        // sweep; the audit trail for each hard-delete still records
        // `userAgent=cli:pim:tenants:purge-deleted`.
        $callerId = Uuid::v7();
        $rows = $this->superAdminContext->runCrossTenant(
            $callerId,
            fn (): array => $this->fetchExpiredCandidates($retention),
        );

        if ([] === $rows) {
            $io->success('No tenants past the retention window — nothing to purge.');

            return Command::SUCCESS;
        }

        $io->section(\sprintf('%d tenant(s) past the %d-day retention window:', \count($rows), $retention));
        foreach ($rows as $row) {
            $io->writeln(\sprintf(
                '  - %s (%s) deleted_at=%s',
                $row['code'],
                $row['id'],
                $row['deleted_at'],
            ));
        }

        if ($dryRun) {
            $io->note('Dry-run: no rows were modified. Re-run without --dry-run to hard-delete.');

            return Command::SUCCESS;
        }

        $deleted = 0;
        foreach ($rows as $row) {
            try {
                $uuid = Uuid::fromString($row['id']);
            } catch (InvalidArgumentException) {
                $io->warning(\sprintf('Skipping malformed tenant id `%s`.', $row['id']));
                continue;
            }
            $tenant = $this->superAdminContext->runCrossTenant(
                $callerId,
                fn () => $this->tenants->findById($uuid),
            );
            if (null === $tenant) {
                continue;
            }
            $this->superAdminContext->runCrossTenant(
                $callerId,
                function () use ($tenant): void {
                    $this->tenants->remove($tenant);
                },
            );
            ++$deleted;
        }

        $io->success(\sprintf('Hard-deleted %d tenant(s).', $deleted));

        return Command::SUCCESS;
    }

    /**
     * @return list<array{id: string, code: string, deleted_at: string}>
     */
    private function fetchExpiredCandidates(int $retentionDays): array
    {
        $sql = <<<'SQL'
            SELECT id, code, deleted_at::text AS deleted_at
            FROM tenants
            WHERE status = 'deleted'
              AND deleted_at IS NOT NULL
              AND deleted_at < (NOW() - (:retention_days || ' days')::interval)
            ORDER BY deleted_at ASC
            SQL;

        $rows = $this->connection->fetchAllAssociative($sql, [
            'retention_days' => (string) $retentionDays,
        ]);

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => \is_string($row['id']) ? $row['id'] : '',
                'code' => \is_string($row['code']) ? $row['code'] : '',
                'deleted_at' => \is_string($row['deleted_at']) ? $row['deleted_at'] : '',
            ];
        }

        return $out;
    }
}
