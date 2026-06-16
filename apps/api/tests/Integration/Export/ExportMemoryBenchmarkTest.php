<?php

declare(strict_types=1);

namespace App\Tests\Integration\Export;

use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\Entity\ObjectTypeAttribute;
use App\Catalog\Domain\ObjectKind;
use App\Export\Application\Sync\SyncExportRunner;
use App\Export\Domain\Entity\ExportSession;
use App\Export\Domain\Enum\ExportFormat;
use App\Export\Domain\Enum\ExportSource;
use App\Export\Domain\Enum\ExportTargetScope;
use App\Export\Domain\Repository\ExportSessionRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use App\Shared\Infrastructure\Doctrine\Filter\TenantFilterConfigurator;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * IMP2-2.6 (#1482) — etap-2 gate: the streaming export path (scope All, masters
 * only) holds a 50k-object export in constant memory instead of materialising
 * the whole object graph. Objects + values are seeded set-based (DB-side SQL,
 * negligible PHP memory) so the measured peak reflects {@see SyncExportRunner}
 * alone. Runs as a dedicated `import-benchmark` CI step.
 */
#[Group('import-benchmark')]
final class ExportMemoryBenchmarkTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private const int THRESHOLD_BYTES = 256 * 1024 * 1024;

    #[Test]
    public function streamingExportOf5kStaysUnderMemoryBudget(): void
    {
        $this->assertExportStreamsWithinBudget(5_000);
    }

    #[Test]
    public function streamingExportOf50kStaysUnderMemoryBudget(): void
    {
        $this->assertExportStreamsWithinBudget(50_000);
    }

    private function assertExportStreamsWithinBudget(int $count): void
    {
        $tenant = $this->seedObjects($count);

        $session = new ExportSession(
            userId: Uuid::v7(),
            source: ExportSource::ListContext,
            format: ExportFormat::Csv,
            targetScope: ExportTargetScope::All,
            selectedColumns: ['sku', 'name'],
            includeVariants: false,
        );
        $session->assignTenant($tenant);
        self::getContainer()->get(ExportSessionRepositoryInterface::class)->save($session);

        $target = tempnam(sys_get_temp_dir(), 'bench-export-').'.csv';
        $runner = self::getContainer()->get(SyncExportRunner::class);

        \memory_reset_peak_usage();
        $before = \memory_get_usage(true);
        $written = $runner->runToFile($session, $target);
        $peak = \memory_get_peak_usage(true);
        @unlink($target);

        self::assertSame($count, $written, 'every seeded root object is exported');
        self::assertLessThan(
            self::THRESHOLD_BYTES,
            $peak,
            \sprintf('export of %d objects peaked at %0.1f MiB, must stay under 256 MiB', $count, $peak / 1024 / 1024),
        );
        // Streaming delta over the pre-run baseline must be a fraction of the
        // full set — proves we never materialise all N objects at once.
        self::assertLessThan(
            128 * 1024 * 1024,
            $peak - $before,
            \sprintf('streaming delta %0.1f MiB must stay bounded', ($peak - $before) / 1024 / 1024),
        );
    }

    private function seedObjects(int $count): Tenant
    {
        $em = $this->em();
        $tenant = new Tenant('bench', 'Bench Tenant');
        $em->persist($tenant);
        $em->flush();
        self::getContainer()->get(TenantContext::class)->set($tenant);

        $type = new ObjectType('product', ObjectKind::Product, ['en' => 'Product']);
        $type->markBuiltIn();
        $em->persist($type);
        $sku = new Attribute('sku', ['en' => 'SKU'], AttributeType::Text);
        $name = new Attribute('name', ['en' => 'Name'], AttributeType::Text);
        $em->persist($sku);
        $em->persist($name);
        $em->persist(new ObjectTypeAttribute($type, $sku, false, 1));
        $em->persist(new ObjectTypeAttribute($type, $name, false, 2));
        $em->flush();

        $tenantId = $tenant->getId()->toRfc4122();
        $conn = $em->getConnection();
        $conn->executeStatement(
            <<<'SQL'
                INSERT INTO objects (id, tenant_id, object_type_id, kind, code, enabled, status, completeness, attributes_indexed, created_at, updated_at, completeness_pct, sync_status_aggregate, version, schema_drift)
                SELECT gen_random_uuid(), :t, :ot, 'product', 'BENCH-'||g, true, 'published', '{}'::jsonb, '{}'::jsonb, now(), now(), 0, 'gray', 1, false
                FROM generate_series(1, :n) g
                SQL,
            ['t' => $tenantId, 'ot' => $type->getId()->toRfc4122(), 'n' => $count],
        );
        foreach ([$sku->getId()->toRfc4122(), $name->getId()->toRfc4122()] as $attrId) {
            $conn->executeStatement(
                <<<'SQL'
                    INSERT INTO object_values (id, tenant_id, object_id, attribute_id, value, provenance, provenance_meta)
                    SELECT gen_random_uuid(), :t, o.id, :a, jsonb_build_object('value', o.code), 'import', '{}'::jsonb
                    FROM objects o WHERE o.tenant_id = :t
                    SQL,
                ['t' => $tenantId, 'a' => $attrId],
            );
        }
        $em->clear();

        $reloaded = $em->getRepository(Tenant::class)->find($tenantId);
        \assert($reloaded instanceof Tenant);
        self::getContainer()->get(TenantContext::class)->set($reloaded);
        self::getContainer()->get(TenantFilterConfigurator::class)->apply();

        return $reloaded;
    }

    private function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }
}
