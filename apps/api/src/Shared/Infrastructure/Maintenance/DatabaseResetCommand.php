<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Maintenance;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Drop + create + migrate + (optional) load fixtures, in a single command.
 *
 * The Sprint-0 dance was three separate `bin/console` calls plus stopping the
 * api container so FrankenPHP would release its persistent connection. This
 * command bundles the SQL side of that workflow — the operator still has to
 * `docker compose stop api` first if the api worker is up. The runtime check
 * tells them so explicitly instead of failing on a confusing "database in use"
 * error from Postgres.
 *
 * Designed for development databases. In production a refusal trips on
 * APP_ENV=prod unless `--force-prod` is passed, so a stray invocation does not
 * delete a pilot tenant's data.
 */
#[AsCommand(
    name: 'pim:db:reset',
    description: 'Drop + create + migrate (+ fixtures) the development database in one shot.'
)]
final class DatabaseResetCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('with-fixtures', null, InputOption::VALUE_NONE, 'Load Doctrine fixtures after migration')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Skip the interactive confirmation prompt')
            ->addOption('force-prod', null, InputOption::VALUE_NONE, 'Allow running against APP_ENV=prod (dangerous)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        // PHPStan max: $_SERVER values are mixed; narrow before any sprintf.
        $envRaw = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? 'dev';
        $env = \is_string($envRaw) ? $envRaw : 'dev';

        if ('prod' === $env && !$input->getOption('force-prod')) {
            $io->error('Refusing to reset the production database. Pass --force-prod to override.');

            return Command::FAILURE;
        }

        if (!$input->getOption('force')) {
            $question = new ConfirmationQuestion(
                sprintf('This drops the <comment>%s</> database. Continue? (y/N) ', $env),
                false
            );
            // SymfonyStyle::askQuestion returns mixed; ConfirmationQuestion
            // resolves to bool, but PHPStan max won't trust that without an
            // explicit comparison.
            if (true !== $io->askQuestion($question)) {
                $io->warning('Aborted.');

                return Command::FAILURE;
            }
        }

        // VALUE_NONE options are typed as bool by phpstan-symfony — no cast.
        $loadFixtures = $input->getOption('with-fixtures');
        $steps = [
            ['doctrine:database:drop', ['--force' => true, '--if-exists' => true]],
            ['doctrine:database:create', ['--if-not-exists' => true]],
            ['doctrine:migrations:migrate', ['--no-interaction' => true, '--allow-no-migration' => true]],
            // dh_auditor creates *_audit tables outside the regular Doctrine
            // migrations pipeline. Without this step, INSERTs into audited
            // entities (ImportSession, ImportProfile, Channel, Asset, …)
            // trip a "relation does not exist" inside the auditor listener,
            // rollback the surrounding transaction, and the operator sees a
            // bare 500 with a foreign-key violation when the parent rows
            // were never actually committed.
            ['audit:schema:update', ['--force' => true]],
        ];

        if ($loadFixtures) {
            $steps[] = ['doctrine:fixtures:load', ['--no-interaction' => true]];
            // Wiping Postgres rotates every tenant UUID (TenantFactory
            // generates fresh ids); without dropping Meili documents the
            // shared `products`/`categories`/… indexes accumulate orphans
            // from previous seeds. Each orphan still carries its old
            // `tenantId` filter value, so admin search hides them — but
            // they break unique-code assumptions, inflate the doc count,
            // and confuse debugging (one `code=DEMO-100` per past seed).
            // The `--purge` flag wipes documents and re-imports cleanly.
            $steps[] = ['pim:search:reindex', ['--kind' => 'all', '--purge' => true]];
        }

        // Wipe stale BulkOperationLock flock files (`sf.bulk-op-{tenant}.lock`)
        // sitting in /tmp from previous tenant generations. The tenant
        // UUID rotates on every fixture reload, so the old lock file
        // never matches the new tenant — but a worker that crashed
        // mid-bulk-run still leaves the lock present for the same
        // tenant ID, blocking subsequent runs for up to the 1h TTL.
        // Resetting the DB is the right point to flush that state.
        $lockDir = sys_get_temp_dir();
        $matches = glob($lockDir.'/sf.bulk-op-*.lock');
        $stale = \is_array($matches) ? $matches : [];
        foreach ($stale as $lockPath) {
            @unlink($lockPath);
        }
        if ([] !== $stale) {
            $io->writeln(sprintf('  cleared %d stale bulk-op lock file(s)', \count($stale)));
        }

        foreach ($steps as [$commandName, $arguments]) {
            $io->section(sprintf('→ %s', $commandName));
            $application = $this->getApplication();
            if (null === $application) {
                $io->error('Console application is not available; refusing to chain commands.');

                return Command::FAILURE;
            }

            $arrayInput = new \Symfony\Component\Console\Input\ArrayInput($arguments);
            // Nested ArrayInput defaults to interactive=true even when the
            // outer command was invoked with --no-interaction. Without this,
            // doctrine:fixtures:load silently aborts on its purge prompt
            // (default answer "no") and pim:db:reset reports success despite
            // an empty fixtures load. Other chained commands (drop/create/
            // migrate) ship a "yes" default so they were never affected.
            $arrayInput->setInteractive(false);

            $exitCode = $application
                ->find($commandName)
                ->run($arrayInput, $output);

            if (Command::SUCCESS !== $exitCode) {
                $io->error(sprintf('Step "%s" failed (exit %d). Aborting.', $commandName, $exitCode));

                return Command::FAILURE;
            }
        }

        $io->success(sprintf(
            'Database reset complete (env=%s%s).',
            $env,
            $loadFixtures ? ', fixtures loaded' : ''
        ));

        return Command::SUCCESS;
    }
}
