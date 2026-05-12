<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * HARD-01 — guard the indexes that hot product-list paths depend on.
 *
 * Ran into a regression on 2026-05-12 (PR #514): tree-mode product
 * list filters by `(tenant_id, parent_id IS NULL)` and without a
 * composite index the planner scans the whole `objects` table at
 * production volume (50k SKU). The test asserts the index is
 * present at the schema level so it cannot silently disappear via
 * a future migration / `pim:db:reset` regen.
 */
final class CatalogObjectIndexesTest extends KernelTestCase
{
    #[Test]
    public function objectsTableHasCompositeTenantParentIndex(): void
    {
        /** @var Connection $connection */
        $connection = self::getContainer()->get(Connection::class);

        $rows = $connection->fetchAllAssociative(<<<'SQL'
            SELECT indexname, indexdef
            FROM pg_indexes
            WHERE tablename = 'objects'
              AND indexname = 'objects_tenant_parent_idx'
            SQL);

        self::assertCount(1, $rows, 'Expected composite index objects_tenant_parent_idx to exist.');
        $indexDef = $rows[0]['indexdef'] ?? null;
        \assert(\is_string($indexDef));
        self::assertStringContainsString('(tenant_id, parent_id)', $indexDef);
    }
}
