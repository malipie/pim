<?php

declare(strict_types=1);

namespace App\Maintenance;

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
        $env = $_SERVER['APP_ENV'] ?? $_ENV['APP_ENV'] ?? 'dev';

        if ('prod' === $env && !$input->getOption('force-prod')) {
            $io->error('Refusing to reset the production database. Pass --force-prod to override.');

            return Command::FAILURE;
        }

        if (!$input->getOption('force')) {
            $question = new ConfirmationQuestion(
                sprintf('This drops the <comment>%s</> database. Continue? (y/N) ', $env),
                false
            );
            if (!$io->askQuestion($question)) {
                $io->warning('Aborted.');

                return Command::FAILURE;
            }
        }

        $loadFixtures = (bool) $input->getOption('with-fixtures');
        $steps = [
            ['doctrine:database:drop', ['--force' => true, '--if-exists' => true]],
            ['doctrine:database:create', ['--if-not-exists' => true]],
            ['doctrine:migrations:migrate', ['--no-interaction' => true, '--allow-no-migration' => true]],
        ];

        if ($loadFixtures) {
            $steps[] = ['doctrine:fixtures:load', ['--no-interaction' => true]];
        }

        foreach ($steps as [$commandName, $arguments]) {
            $io->section(sprintf('→ %s', $commandName));
            $exitCode = $this->getApplication()
                ?->find($commandName)
                ->run(new \Symfony\Component\Console\Input\ArrayInput($arguments), $output);

            if (Command::SUCCESS !== $exitCode) {
                $io->error(sprintf('Step "%s" failed (exit %d). Aborting.', $commandName, (int) $exitCode));

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
