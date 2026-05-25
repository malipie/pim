<?php

declare(strict_types=1);

namespace App\Search\Presentation\Command;

use App\Search\Infrastructure\MeilisearchClientFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * ULV-02 (#983) one-shot cleanup: drop the pre-ULV per-kind Meilisearch
 * indexes (`products`, `categories`, `assets`, `brands`) after the
 * consolidated `objects` index has been provisioned and reindexed.
 *
 * Run sequence for the cutover:
 *   1. `pim:search:health`        — provisions the new `objects` index
 *   2. `pim:search:reindex --purge` — populates `objects` from Postgres
 *   3. `pim:search:cleanup-legacy-indexes` — drops the four old indexes
 *
 * Skips deletion when the cluster does not contain a given legacy
 * index — repeatable safe.
 */
#[AsCommand(
    name: 'pim:search:cleanup-legacy-indexes',
    description: 'Drop pre-ULV per-kind Meilisearch indexes (products/categories/assets/brands).',
)]
final class SearchCleanupLegacyIndexesCommand extends Command
{
    private const array LEGACY_INDEXES = ['products', 'categories', 'assets', 'brands'];

    public function __construct(
        private readonly MeilisearchClientFactory $clientFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Report which legacy indexes would be dropped without deleting them.',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = true === $input->getOption('dry-run');

        try {
            $client = $this->clientFactory->create();
        } catch (Throwable $e) {
            $io->error('Cannot reach Meilisearch: '.$e->getMessage());

            return Command::FAILURE;
        }

        $present = [];
        foreach ($client->getIndexes()->getResults() as $index) {
            $present[] = $index->getUid();
        }

        $rows = [];
        foreach (self::LEGACY_INDEXES as $name) {
            $exists = \in_array($name, $present, true);
            if (!$exists) {
                $rows[] = [$name, 'absent', 'skip'];

                continue;
            }
            if ($dryRun) {
                $rows[] = [$name, 'present', 'would delete'];

                continue;
            }
            try {
                $client->deleteIndex($name);
                $rows[] = [$name, 'present', 'deleted'];
            } catch (Throwable $e) {
                $rows[] = [$name, 'present', 'FAILED — '.$e->getMessage()];
            }
        }

        $io->table(['Index', 'State', 'Action'], $rows);

        return Command::SUCCESS;
    }
}
