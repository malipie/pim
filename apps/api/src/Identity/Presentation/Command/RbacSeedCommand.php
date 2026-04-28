<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Command;

use App\Identity\Application\RbacSeeder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Idempotent CLI entry point for the RBAC seeder.
 *
 * Operators run this once after `doctrine:migrations:migrate` to provision
 * the four built-in roles. Re-running is safe: a no-op run prints zeros and
 * exits 0.
 */
#[AsCommand(
    name: 'pim:rbac:seed',
    description: 'Seed built-in RBAC roles and permissions (idempotent).',
)]
final class RbacSeedCommand extends Command
{
    public function __construct(
        private readonly RbacSeeder $seeder,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $report = $this->seeder->seed();

        $io->definitionList(
            ['Permissions created' => (string) $report->permissionsCreated],
            ['Roles created' => (string) $report->rolesCreated],
            ['Roles updated' => (string) $report->rolesUpdated],
        );

        if ($report->isNoOp()) {
            $io->success('RBAC matrix already in sync.');
        } else {
            $io->success('RBAC matrix seeded.');
        }

        return Command::SUCCESS;
    }
}
