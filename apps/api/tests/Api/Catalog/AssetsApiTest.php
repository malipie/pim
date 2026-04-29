<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use PHPUnit\Framework\Attributes\Test;

use const JSON_THROW_ON_ERROR;

/**
 * `/api/assets` is read-only on the CatalogObject side in this PR —
 * uploads land via the dedicated multipart pipeline (#37 / 0.3.7);
 * see {@see CatalogObject.assets.xml}.
 */
final class AssetsApiTest extends CatalogApiTestCase
{
    #[Test]
    public function getCollectionReturnsEmptyListForFreshTenant(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('GET', '/api/assets');

        self::assertResponseIsSuccessful();
        $body = $response->toArray();
        self::assertSame(0, $body['totalItems'] ?? -1);
    }

    #[Test]
    public function postReturns405(): void
    {
        $client = $this->authenticatedClient();
        $client->request('POST', '/api/assets', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode(['code' => 'x'], JSON_THROW_ON_ERROR),
        ]);

        self::assertResponseStatusCodeSame(405);
    }
}
