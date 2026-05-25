<?php

declare(strict_types=1);

namespace App\Search\Presentation\Command;

use App\Catalog\Application\BulkContext;
use App\Catalog\Domain\ObjectKind;
use App\Search\Application\BulkCatalogObjectIndexer;
use App\Search\Application\IndexSettingsTemplate;
use App\Search\Infrastructure\MeilisearchClientFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

/**
 * `pim:search:reindex` (#51 / 0.5.3) — full or per-kind rebuild of the
 * Meilisearch indexes from Postgres source-of-truth.
 *
 * Invocations:
 *   - `--kind=product|category|asset` — rebuild a single kind.
 *   - no kind / `--kind=all` — rebuild every built-in kind.
 *   - `--dry-run` — count rows + log the would-be batches without
 *     pushing to Meili. Useful to verify cardinality before a long run.
 *
 * Memory ceiling: lessons #13 documents 50k SKU FLAT at 14 MiB peak with
 * `EntityManager::clear()` every 200 rows; this command inherits the
 * same iterator + clear cadence via {@see BulkCatalogObjectIndexer}.
 *
 * `BulkContext::setBulk(true)` is set during the run so the per-event
 * subscriber from #50 short-circuits — without that the listener would
 * also push every row + we'd duplicate work.
 */
#[AsCommand(
    name: 'pim:search:reindex',
    description: 'Memory-safe full or per-kind reindex of CatalogObject rows into Meilisearch.',
)]
final class SearchReindexCommand extends Command
{
    public function __construct(
        private readonly BulkCatalogObjectIndexer $indexer,
        private readonly BulkContext $bulkContext,
        private readonly MeilisearchClientFactory $clientFactory,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'kind',
                null,
                InputOption::VALUE_REQUIRED,
                'ObjectKind value (`product`, `category`, `asset`) or `all` for the whole catalog.',
                'all',
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Iterate the catalog + log batches but do not push to Meili.',
            )
            ->addOption(
                'purge',
                null,
                InputOption::VALUE_NONE,
                'Delete every existing document from the targeted index before reindex. Use after pim:db:reset to drop orphans from previous tenants.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $kindOption = $input->getOption('kind');

        $kind = null;
        if ('all' !== $kindOption) {
            $kind = ObjectKind::tryFrom($kindOption);
            if (null === $kind) {
                $io->error(\sprintf('Unknown kind "%s". Use product, category, asset, brand, custom, or all.', $kindOption));

                return Command::INVALID;
            }
            // ULV-02 (#983) — custom kinds now land in the consolidated
            // `objects` index alongside built-ins.
        }

        $dryRun = true === $input->getOption('dry-run');
        $purge = true === $input->getOption('purge');

        $io->title(\sprintf(
            '%s reindex of %s into %s%s',
            $dryRun ? 'DRY-RUN' : 'Live',
            null === $kind ? 'every kind' : $kind->value,
            IndexSettingsTemplate::indexName(),
            $purge ? ' (purging existing docs first)' : '',
        ));

        if ($purge && !$dryRun) {
            $this->purgeIndexes($kind, $io);
        }

        $progress = new ProgressBar($output);
        $progress->setFormat('verbose');
        $progress->start();

        // Bulk context off for the per-event listener (it skips by design)
        // but on for any other Catalog flush during the run — the listener
        // contract says "bulk = skip", and reindex IS bulk.
        $this->bulkContext->setBulk(true);

        try {
            $stats = $this->indexer->reindex(
                kind: $kind,
                dryRun: $dryRun,
                onProgress: static function (int $indexed, int $batchSize) use ($progress): void {
                    $progress->advance($batchSize);
                },
            );
        } finally {
            $this->bulkContext->setBulk(false);
            $progress->finish();
            $io->newLine(2);
        }

        $io->success(\sprintf(
            '%s — indexed %d row(s) in %d batch(es).',
            $dryRun ? 'Dry run complete' : 'Reindex complete',
            $stats['count'],
            $stats['batches'],
        ));

        return Command::SUCCESS;
    }

    private function purgeIndexes(?ObjectKind $kind, SymfonyStyle $io): void
    {
        try {
            $client = $this->clientFactory->create();
        } catch (Throwable $e) {
            $io->warning(\sprintf('Cannot reach Meilisearch to purge: %s. Continuing with reindex anyway.', $e->getMessage()));

            return;
        }

        // ULV-02 (#983) — single `objects` index. When `--kind=foo` is set
        // we purge only documents of that kind via filter; full purge drops
        // everything in the index.
        $name = IndexSettingsTemplate::indexName();
        try {
            if (null === $kind) {
                $client->index($name)->deleteAllDocuments();
                $io->writeln(\sprintf('  purged all documents in: %s', $name));
            } else {
                $client->index($name)->deleteDocuments(['filter' => \sprintf('kind = "%s"', $kind->value)]);
                $io->writeln(\sprintf('  purged kind=%s documents in: %s', $kind->value, $name));
            }
        } catch (Throwable $e) {
            $io->warning(\sprintf('Purge of "%s" failed: %s', $name, $e->getMessage()));
        }
    }
}
