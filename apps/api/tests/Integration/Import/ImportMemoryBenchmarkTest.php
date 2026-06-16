<?php

declare(strict_types=1);

namespace App\Tests\Integration\Import;

use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\Entity\ObjectTypeAttribute;
use App\Catalog\Domain\ObjectKind;
use App\Import\Application\Handler\ImportRunHandler;
use App\Import\Domain\Entity\ImportSession;
use App\Import\Domain\Repository\ImportSessionRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use App\Shared\Infrastructure\Doctrine\Filter\TenantFilterConfigurator;
use App\Tests\Support\InMemoryMercureHub;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * IMP2-2.6 (#1482) — etap-2 memory gate, import half. Drives a 5k-row import
 * through {@see ImportRunHandler::run()} on the test Postgres and asserts the
 * production import worker stays under the 256 MiB FrankenPHP budget.
 *
 * What is measured — and what is NOT. The figure that matters is
 * {@see ImportRunHandler::rowPhasePeakBytes()}: the peak the import worker pays
 * for its own job (read → write → flush/clear). Memory is O(chunk) there —
 * BulkContext keeps the per-flush rebuild off and the touched-id list is the
 * only O(total) structure (RFC4122 strings, ~40 B each). The attributes_indexed
 * rebuild + Meilisearch reindex run in a SEPARATE worker in prod (the
 * `async`/`import` transport), bounded by {@see \App\Shared\Application\AbstractBatchHandler}'s
 * per-batch clear (guarded independently by `pim:benchmark:bulk-import`).
 *
 * Measured prod-faithfully, the import worker is flat: ~95 MiB at 50k rows (and
 * 5k/10k sit in the same band) — O(chunk), no per-row growth. Three dev/test
 * artifacts otherwise inflate the raw process peak, none with a prod counterpart:
 *   1. `async` = `sync://` in dev/test → the rebuild runs INLINE in this process
 *      (prod offloads it to a bounded worker). We assert on the row-phase peak,
 *      captured before dispatch, and lift the process memory_limit so the inline
 *      rebuild cannot abort the harness — the gate is the assertion, not a crash.
 *   2. The in-memory test hub retains every Mercure Update (prod POSTs + discards)
 *      → {@see InMemoryMercureHub::stopRetaining()}.
 *   3. With `APP_DEBUG=1` (the local default for `test`) DoctrineBundle's
 *      DebugDataHolder accumulates EVERY SQL statement in memory — a false
 *      ~15 MiB/1k slope that hits ~815 MiB at 50k. CI runs this step with
 *      `APP_DEBUG=0`; an on-demand 50k slope check MUST too, or it measures the
 *      profiler, not the worker.
 *
 * Row count defaults to 5k (operator decision — a 50k run adds ~10-15 min to the
 * job and 5k already proves the flat profile); override with `IMPORT_BENCH_ROWS`
 * (run with `APP_DEBUG=0` per artifact 3). Runs only in the dedicated
 * `import-benchmark` CI step (excluded from the default suite via
 * phpunit.dist.xml <groups>).
 */
#[Group('import-benchmark')]
final class ImportMemoryBenchmarkTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private const int THRESHOLD_BYTES = 256 * 1024 * 1024;

    #[Test]
    public function bulkImportStaysUnderImportWorkerMemoryBudget(): void
    {
        // Artifact 1 (see class docblock): with `async` = `sync://` the rebuild
        // runs inline after the row phase in THIS process and can push the peak
        // past 256 MiB — a limit a fresh prod rebuild worker never sees. Lift the
        // cap so it cannot abort the harness; the gate is the row-phase assertion.
        $originalLimit = \ini_get('memory_limit');
        \ini_set('memory_limit', '-1');

        $envRows = getenv('IMPORT_BENCH_ROWS');
        $count = \is_string($envRows) && '' !== $envRows ? (int) $envRows : 5000;
        $em = $this->em();

        $tenant = new Tenant('bench', 'Bench Tenant');
        $em->persist($tenant);
        $em->flush();
        self::getContainer()->get(TenantContext::class)->set($tenant);

        $type = new ObjectType('product', ObjectKind::Product, ['en' => 'Product']);
        $em->persist($type);
        $sku = new Attribute('sku', ['en' => 'SKU'], AttributeType::Text);
        $name = new Attribute('name', ['en' => 'Name'], AttributeType::Text);
        $em->persist($sku);
        $em->persist($name);
        $em->persist(new ObjectTypeAttribute($type, $sku, false, 1));
        $em->persist(new ObjectTypeAttribute($type, $name, false, 2));

        $session = new ImportSession(
            userId: Uuid::v7(),
            targetObjectType: $type,
            fileName: 'bench.csv',
            fileSizeBytes: 1024,
        );
        $session->assignTenant($tenant);
        $session->setColumnMapping(['sku' => 'sku', 'name' => 'name']);
        $em->persist($session);
        $em->flush();
        $sessionId = $session->getId();

        // Stage the CSV exactly where ImportRunHandler::stageSourceFile reads it.
        $csv = "sku;name\n";
        for ($i = 1; $i <= $count; ++$i) {
            $csv .= \sprintf("BENCH-%d;Bench Product %d\n", $i, $i);
        }
        // get('imports.storage') is typed as the concrete Flysystem Filesystem.
        $storage = self::getContainer()->get('imports.storage');
        $storage->write(
            \sprintf('%s/%s/bench.csv', $tenant->getId()->toRfc4122(), $sessionId->toRfc4122()),
            $csv,
        );

        self::getContainer()->get(TenantFilterConfigurator::class)->apply();
        // Prod hub POSTs over HTTP and discards; the in-memory test hub would
        // otherwise retain every per-row Update (O(total)) — a test artifact.
        $hub = self::getContainer()->get(InMemoryMercureHub::class);
        \assert($hub instanceof InMemoryMercureHub);
        $hub->stopRetaining();
        $handler = self::getContainer()->get(ImportRunHandler::class);

        \memory_reset_peak_usage();
        $before = \memory_get_usage(true);
        $handler->run($session);

        // The import worker's own peak — before the rebuild/reindex the prod
        // `async`/`import` worker handles separately. This is the figure a
        // 256 MiB FrankenPHP import worker actually pays.
        $peak = $handler->rowPhasePeakBytes();
        \ini_set('memory_limit', $originalLimit);
        if (null === $peak) {
            self::fail('row phase must reach the rebuild dispatch point');
        }

        // run() clears the EM; reload the finalized session for its counters.
        self::getContainer()->get(TenantContext::class)->set($tenant);
        $reloaded = self::getContainer()->get(ImportSessionRepositoryInterface::class)->findById($sessionId);
        self::assertInstanceOf(ImportSession::class, $reloaded);
        self::assertSame($count, $reloaded->getSuccessCount(), 'every benchmark row imported');

        self::assertLessThan(
            self::THRESHOLD_BYTES,
            $peak,
            \sprintf('import worker peaked at %0.1f MiB for %d rows, must stay under 256 MiB', $peak / 1024 / 1024, $count),
        );
        self::assertLessThan(
            160 * 1024 * 1024,
            $peak - $before,
            \sprintf('import worker memory delta %0.1f MiB must stay bounded', ($peak - $before) / 1024 / 1024),
        );
    }

    private function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }
}
