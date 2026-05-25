<?php

declare(strict_types=1);

namespace App\Search\Presentation\Command;

use App\Search\Application\IndexSettingsTemplate;
use App\Search\Application\MeilisearchIndexProvisioner;
use App\Search\Infrastructure\MeilisearchClientFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * `pim:search:health` (#49 / 0.5.1) — verifies the Meilisearch hub is
 * reachable and provisions the per-kind indexes (idempotent).
 *
 * Used by:
 *   - operators after first stack-up — quick "did Meili come up?" check;
 *   - the smoke check at the end of fixture loads;
 *   - CI later (when we add a `make smoke` target).
 *
 * Exit code 0 = healthy + indexes provisioned. 1 = unreachable
 * (network / wrong key / hub not started).
 */
#[AsCommand(
    name: 'pim:search:health',
    description: 'Verify Meilisearch reachability and provision per-kind indexes.',
)]
final class SearchHealthCommand extends Command
{
    public function __construct(
        private readonly MeilisearchClientFactory $clientFactory,
        private readonly MeilisearchIndexProvisioner $provisioner,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $client = $this->clientFactory->create();

        try {
            $health = $client->health();
        } catch (Throwable $e) {
            $io->error('Meilisearch unreachable: '.$e->getMessage());

            return Command::FAILURE;
        }

        $rawStatus = $health['status'] ?? 'unknown';
        $status = \is_scalar($rawStatus) ? (string) $rawStatus : 'unknown';
        if ('available' !== $status) {
            $io->error(\sprintf('Meilisearch reported status="%s" (expected "available").', $status));

            return Command::FAILURE;
        }

        $io->success(\sprintf('Meilisearch healthy (status=%s).', $status));

        // ULV-02 (#983) — one consolidated `objects` index covers every
        // kind. The health command now reports a single row.
        $tasks = $this->provisioner->provision();
        $name = IndexSettingsTemplate::indexName();
        $io->table(
            ['Index', 'Indexed kinds', 'Settings task UID'],
            [[
                $name,
                implode(', ', array_map(static fn ($k) => $k->value, IndexSettingsTemplate::indexedKinds())),
                $tasks[$name] ?? '-',
            ]],
        );

        return Command::SUCCESS;
    }
}
