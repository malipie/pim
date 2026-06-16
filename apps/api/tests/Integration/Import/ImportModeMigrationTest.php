<?php

declare(strict_types=1);

namespace App\Tests\Integration\Import;

use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * IMP2-1.3 (#1465, ADR-0019 D3) — the import-mode migration
 * {@see \DoctrineMigrations\Version20260612230000} collapses the never-built
 * legacy modes onto the real CREATE/UPDATE/UPSERT triplet: ADD → CREATE and
 * MERGE/INCREMENT/DELETE → UPSERT.
 *
 * The test schema is built from ORM metadata, not by replaying migrations, so
 * the legacy rows are seeded with raw INSERTs (the entity enum no longer accepts
 * those values) and the migration's data SQL is replayed directly on the
 * connection — exactly the statements `up()` emits.
 */
final class ImportModeMigrationTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function legacyModesAreRemappedToCreateAndUpsert(): void
    {
        $em = $this->em();
        $connection = $em->getConnection();

        $tenant = new Tenant('mode-mig', 'Mode Migration Tenant');
        $em->persist($tenant);
        $em->flush();
        $this->tenantContext()->set($tenant);
        $objectType = $this->productObjectType($em);

        $tenantId = $tenant->getId()->toRfc4122();
        $objectTypeId = $objectType->getId()->toRfc4122();
        $userId = Uuid::v7()->toRfc4122();

        // Seed one profile per legacy mode with a raw INSERT — the ImportMode
        // enum (and the entity hydration) would reject these values now.
        $legacy = [
            'add' => 'ADD',
            'merge' => 'MERGE',
            'increment' => 'INCREMENT',
            'delete' => 'DELETE',
        ];
        foreach ($legacy as $code => $mode) {
            $id = Uuid::v7()->toRfc4122();
            $connection->insert('import_profiles', [
                'id' => $id,
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'name' => 'Legacy '.$mode,
                'code' => $code,
                'mode' => $mode,
                'target_object_type_id' => $objectTypeId,
                'column_mapping' => '{}',
                'image_source' => 'none',
                'created_at' => new DateTimeImmutable()->format('Y-m-d H:i:s'),
            ]);
        }

        // Replay the migration's data SQL (Version20260612230000::up).
        $connection->executeStatement("UPDATE import_profiles SET mode = 'CREATE' WHERE mode = 'ADD'");
        $connection->executeStatement("UPDATE import_profiles SET mode = 'UPSERT' WHERE mode IN ('MERGE', 'INCREMENT', 'DELETE')");

        $modeFor = static function (string $code) use ($connection, $tenantId): string {
            $value = $connection->fetchOne(
                'SELECT mode FROM import_profiles WHERE tenant_id = :tenant AND code = :code',
                ['tenant' => $tenantId, 'code' => $code],
            );

            return \is_scalar($value) ? (string) $value : '';
        };

        self::assertSame('CREATE', $modeFor('add'), 'ADD → CREATE');
        self::assertSame('UPSERT', $modeFor('merge'), 'MERGE → UPSERT');
        self::assertSame('UPSERT', $modeFor('increment'), 'INCREMENT → UPSERT');
        self::assertSame('UPSERT', $modeFor('delete'), 'DELETE → UPSERT');

        // No legacy value survives the remap.
        $legacyLeft = $connection->fetchOne(
            "SELECT COUNT(*) FROM import_profiles WHERE tenant_id = :tenant AND mode IN ('ADD', 'MERGE', 'INCREMENT', 'DELETE')",
            ['tenant' => $tenantId],
        );
        self::assertSame(0, (int) (\is_scalar($legacyLeft) ? $legacyLeft : 1), 'no legacy mode value remains');
    }

    private function productObjectType(EntityManagerInterface $em): ObjectType
    {
        $type = $em->getRepository(ObjectType::class)->findOneBy(['kind' => ObjectKind::Product]);
        if ($type instanceof ObjectType) {
            return $type;
        }

        $type = new ObjectType('product', ObjectKind::Product, ['en' => 'Product']);
        $em->persist($type);
        $em->flush();

        return $type;
    }

    private function tenantContext(): TenantContext
    {
        return self::getContainer()->get(TenantContext::class);
    }

    private function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }
}
