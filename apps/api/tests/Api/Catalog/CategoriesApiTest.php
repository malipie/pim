<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use App\Catalog\Domain\ObjectKind;
use PHPUnit\Framework\Attributes\Test;

use const JSON_THROW_ON_ERROR;

final class CategoriesApiTest extends CatalogApiTestCase
{
    #[Test]
    public function postCreatesCategoryRow(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('POST', '/api/categories', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'electronics',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Category),
            ], JSON_THROW_ON_ERROR),
        ]);

        self::assertResponseStatusCodeSame(201);
        $body = $response->toArray();
        self::assertSame('electronics', $body['code'] ?? null);
        self::assertSame('category', $body['kind'] ?? null);
    }

    #[Test]
    public function postWithProductObjectTypeReturns422(): void
    {
        $client = $this->authenticatedClient();
        $client->request('POST', '/api/categories', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'mismatch',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ]);

        self::assertResponseStatusCodeSame(422);
    }
}
