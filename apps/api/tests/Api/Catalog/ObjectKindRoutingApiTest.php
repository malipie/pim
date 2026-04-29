<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use App\Catalog\Domain\ObjectKind;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;

use const JSON_THROW_ON_ERROR;

/**
 * DoD-defining test for #41: POST `/api/products` and POST `/api/categories`
 * end up in the same `objects` table, with the right `kind` column. The two
 * sugar paths share one entity and one table — the discriminator is in the
 * column, not the URL prefix.
 */
final class ObjectKindRoutingApiTest extends CatalogApiTestCase
{
    #[Test]
    public function bothSugarPathsLandInSameTableWithDistinctKinds(): void
    {
        $client = $this->authenticatedClient();
        $client->request('POST', '/api/products', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'PROD-1',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(201);

        $client->request('POST', '/api/categories', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'CAT-1',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Category),
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(201);

        $connection = self::getContainer()->get('doctrine')->getConnection();
        \assert($connection instanceof Connection);

        /** @var array<int, array{kind: string, count: int}> $rows */
        $rows = $connection->createQueryBuilder()
            ->select('kind', 'COUNT(*) AS count')
            ->from('objects')
            ->groupBy('kind')
            ->orderBy('kind')
            ->executeQuery()
            ->fetchAllAssociative();

        $byKind = [];
        foreach ($rows as $row) {
            $byKind[$row['kind']] = $row['count'];
        }

        self::assertGreaterThanOrEqual(1, $byKind['product'] ?? 0, 'Product POST must land in objects table.');
        self::assertGreaterThanOrEqual(1, $byKind['category'] ?? 0, 'Category POST must land in objects table.');
    }
}
