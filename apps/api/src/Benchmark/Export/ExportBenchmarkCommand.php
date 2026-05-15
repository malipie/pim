<?php

declare(strict_types=1);

namespace App\Benchmark\Export;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Export\Application\Builder\ExportBuilder;
use App\Export\Domain\Entity\ExportSession;
use App\Export\Domain\Enum\ExportFormat;
use App\Export\Domain\Enum\ExportSource;
use App\Export\Domain\Enum\ExportTargetScope;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

/**
 * EXP-04 (#583) — Export performance benchmark.
 *
 * Walks the {@see ExportBuilder} contract under realistic chunking + memory
 * conditions so the EXP-06 async handler can pick its `N` chunk size with
 * data rather than guesswork. PRD §11.2 target: <30s sync write 50k SKU
 * × 30 columns to MinIO. This command does NOT write to MinIO yet (EXP-05
 * lands the OpenSpout streaming writer) — it isolates the iterator + JSONB
 * decoding hot path so memory + per-row latency are measurable in
 * isolation.
 *
 * Inputs:
 *   --tenant=<code>      Tenant scope for the run (default: APP_DEFAULT_TENANT_CODE).
 *   --limit=<N>          How many objects to walk (default: 1000).
 *   --chunk=<N>          EntityManager::clear() cadence (default: 1000).
 *   --columns=<csv>      Column keys to emit (default: a 10-column spread).
 *
 * Output:
 *   - `agent/exp-04-perf-benchmark.md` snapshot row with timestamp + numbers
 *     (appended, not overwritten — historical trend over runs is the
 *     point).
 *   - Stdout summary (rows, ms, MB peak, ms/row, MB/1000-rows).
 *
 * Caveats:
 *   - Demo dataset in dev tops out around a few hundred products; running
 *     with --limit=50000 requires a synthetic seeder which is the next
 *     POC step. The chunking + memory contract still holds at smaller
 *     scale — the regression risk is per-row JSONB envelope cost, not
 *     anything that scales super-linearly.
 *   - PHPStan keeps the command well-typed; the file lives under
 *     `src/Benchmark/Export/` so production deploys can opt it out via
 *     deptrac (no domain class depends on it).
 */
#[AsCommand(
    name: 'pim:export:benchmark',
    description: 'Walk ExportBuilder over N products and report time + memory (EXP-04 POC).'
)]
final class ExportBenchmarkCommand extends Command
{
    private const DEFAULT_COLUMNS = [
        'sku',
        'parent_sku',
        'status',
        'enabled',
        'completeness_pct',
        'created_at',
        'updated_at',
        'category',
        'name',
        'description',
    ];

    private const REPORT_PATH = 'agent/exp-04-perf-benchmark.md';

    public function __construct(
        private readonly ExportBuilder $builder,
        private readonly CatalogObjectRepositoryInterface $objects,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('tenant', null, InputOption::VALUE_REQUIRED, 'Tenant code to scope the benchmark', 'demo')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Number of objects to walk', '1000')
            ->addOption('chunk', null, InputOption::VALUE_REQUIRED, 'EntityManager::clear() cadence', '1000')
            ->addOption('columns', null, InputOption::VALUE_REQUIRED, 'Comma-separated column keys', implode(',', self::DEFAULT_COLUMNS))
            ->addOption('no-report', null, InputOption::VALUE_NONE, 'Skip appending to agent/exp-04-perf-benchmark.md');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $tenantCode = $this->stringOption($input, 'tenant');
        $limit = max(1, (int) $input->getOption('limit'));
        $chunk = max(1, (int) $input->getOption('chunk'));
        $columnsCsv = $this->stringOption($input, 'columns');
        $columns = array_values(array_filter(
            array_map('trim', explode(',', $columnsCsv)),
            static fn (string $col): bool => '' !== $col,
        ));

        $tenant = $this->resolveTenant($tenantCode);
        if (null === $tenant) {
            $io->error(sprintf('Tenant "%s" not found.', $tenantCode));

            return Command::FAILURE;
        }

        $objects = $this->loadObjects($tenant, $limit);
        $actual = \count($objects);
        if (0 === $actual) {
            $io->warning('No CatalogObjects found for this tenant — seed the demo dataset first.');

            return Command::FAILURE;
        }

        $session = $this->newSession($columns, $tenant);
        $io->section(sprintf(
            'Benchmark: tenant=%s, objects=%d (requested %d), chunk=%d, columns=%d',
            $tenantCode,
            $actual,
            $limit,
            $chunk,
            \count($columns),
        ));

        gc_collect_cycles();
        $baselineMem = memory_get_usage(true);
        $startedAt = microtime(true);
        $peakMem = $baselineMem;
        $rows = 0;

        foreach ($this->builder->build($objects, $session) as $_row) {
            ++$rows;
            if (0 === $rows % $chunk) {
                $this->entityManager->clear();
                gc_collect_cycles();
                $peakMem = max($peakMem, memory_get_usage(true));
            }
        }
        $peakMem = max($peakMem, memory_get_usage(true));
        $elapsed = microtime(true) - $startedAt;

        $elapsedMs = $elapsed * 1000.0;
        $msPerRow = $rows > 0 ? $elapsedMs / $rows : 0.0;
        $bytesGrowth = $peakMem - $baselineMem;
        $mbPeak = $peakMem / 1024 / 1024;

        $io->definitionList(
            ['rows produced' => (string) $rows],
            ['elapsed' => sprintf('%.2f ms', $elapsedMs)],
            ['per row' => sprintf('%.3f ms', $msPerRow)],
            ['peak memory' => sprintf('%.2f MB', $mbPeak)],
            ['memory growth' => sprintf('%.2f MB', $bytesGrowth / 1024 / 1024)],
            ['extrapolated to 50k SKU' => sprintf('%.2f s', $msPerRow * 50_000 / 1000)],
        );

        if (!$input->getOption('no-report')) {
            $this->appendReport($tenantCode, $actual, $chunk, $columns, $rows, $elapsedMs, $peakMem, $bytesGrowth);
            $io->success(sprintf('Snapshot appended to %s', self::REPORT_PATH));
        }

        return Command::SUCCESS;
    }

    private function stringOption(InputInterface $input, string $name): string
    {
        $value = $input->getOption($name);

        return is_string($value) ? $value : '';
    }

    /**
     * @return list<CatalogObject>
     */
    private function loadObjects(Tenant $tenant, int $limit): array
    {
        // Demo dataset typically holds a few hundred products under
        // `kind=product`. We fetch them via the existing repository so
        // tenant filter + Doctrine lifecycle behave like in production.
        $all = $this->objects->findByKind(\App\Catalog\Domain\ObjectKind::Product, $tenant);

        return \array_slice($all, 0, $limit);
    }

    private function resolveTenant(string $code): ?Tenant
    {
        $repo = $this->entityManager->getRepository(Tenant::class);
        $tenant = $repo->findOneBy(['code' => $code]);

        return $tenant instanceof Tenant ? $tenant : null;
    }

    /**
     * @param list<string> $columns
     */
    private function newSession(array $columns, Tenant $tenant): ExportSession
    {
        $session = new ExportSession(
            userId: Uuid::v7(),
            source: ExportSource::SavedProfileRun,
            format: ExportFormat::Xlsx,
            targetScope: ExportTargetScope::All,
            selectedColumns: $columns,
        );
        $session->assignTenant($tenant);

        return $session;
    }

    /**
     * @param list<string> $columns
     */
    private function appendReport(
        string $tenantCode,
        int $loaded,
        int $chunk,
        array $columns,
        int $rows,
        float $elapsedMs,
        int $peakMem,
        int $bytesGrowth,
    ): void {
        $path = $this->projectDir.'/'.self::REPORT_PATH;
        if (!is_dir(\dirname($path))) {
            @mkdir(\dirname($path), 0o755, true);
        }

        $timestamp = new DateTimeImmutable()->format(DateTimeInterface::ATOM);
        $msPerRow = $rows > 0 ? $elapsedMs / $rows : 0.0;
        $extrap50k = $msPerRow * 50_000 / 1000;

        $row = sprintf(
            "| %s | %s | %d | %d | %d | %.2f | %.3f | %.2f | %.2f | %.2f |\n",
            $timestamp,
            $tenantCode,
            $loaded,
            $chunk,
            \count($columns),
            $elapsedMs,
            $msPerRow,
            $peakMem / 1024 / 1024,
            $bytesGrowth / 1024 / 1024,
            $extrap50k,
        );

        $needsHeader = !file_exists($path);
        $fh = fopen($path, 'a');
        if (false === $fh) {
            return;
        }
        try {
            if ($needsHeader) {
                fwrite($fh, $this->reportHeader());
            }
            fwrite($fh, $row);
        } finally {
            fclose($fh);
        }
    }

    private function reportHeader(): string
    {
        return <<<MD
            # EXP-04 — Export performance benchmark log

            > **Ticket:** [#583](https://github.com/malipie/PIM/issues/583)
            >
            > Append-only run log produced by `bin/console pim:export:benchmark`.
            > Each row is a single benchmark run captured from the developer or CI
            > environment. PRD §11.2 target: <30s for 50k SKU × 30 columns sync
            > write. The "extrapolated 50k" column projects the per-row latency to
            > that scale so trends are visible even when the local dataset is
            > smaller than production.
            >
            > **How to interpret:**
            > - `rows`: actual objects walked (demo dataset is small; expect a few
            >   hundred until the synthetic seeder lands).
            > - `elapsed_ms`, `ms_per_row`: builder + repo cost only — XLSX writer
            >   lands in EXP-05.
            > - `peak_mb`: `memory_get_usage(true)` peak after `EntityManager::clear()`
            >   at the chunk cadence. Should stay flat as rows grow — CLAUDE.md
            >   §3.10 worker-mode guardrail.
            > - `growth_mb`: peak minus baseline. Anything >10 MB on a small dataset
            >   is a smell.
            > - `extrap_50k_s`: pessimistic projection assuming linear scaling.

            | timestamp | tenant | rows | chunk | cols | elapsed_ms | ms_per_row | peak_mb | growth_mb | extrap_50k_s |
            |---|---|---|---|---|---|---|---|---|---|

            MD;
    }
}
