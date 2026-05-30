<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use App\Catalog\Domain\ObjectKind;
use PHPUnit\Framework\Attributes\Test;

use const JSON_THROW_ON_ERROR;

/**
 * VIEW-07 (#420) — coverage for `POST /api/products/{id}/duplicate`
 * sugar endpoint behind {@see \App\Catalog\Presentation\Controller\DuplicateProductController}.
 *
 * Sibling of {@see ProductsApiTest}; isolated suite keeps the duplicate
 * scenarios self-documenting and lets future dive-ins (assets, related)
 * extend without bloating the base CRUD suite.
 */
final class DuplicateProductApiTest extends CatalogApiTestCase
{
    #[Test]
    public function postDuplicateAutoGeneratesCopySku(): void
    {
        $client = $this->authenticatedClient();
        $created = $client->request('POST', '/api/products', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'TST-100',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ])->toArray();

        $id = $created['id'] ?? null;
        \assert(\is_string($id));

        $response = $client->request('POST', '/api/products/'.$id.'/duplicate', [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([], JSON_THROW_ON_ERROR),
        ]);

        self::assertResponseStatusCodeSame(201);
        $body = $response->toArray();
        self::assertSame('TST-100-COPY-1', $body['code'] ?? null);
        self::assertSame('product', $body['kind'] ?? null);
        self::assertSame($id, $body['source_id'] ?? null);
        self::assertNotSame($id, $body['id'] ?? null);
    }

    #[Test]
    public function postDuplicateRespectsExplicitSku(): void
    {
        $client = $this->authenticatedClient();
        $created = $client->request('POST', '/api/products', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'TST-200',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ])->toArray();

        $id = $created['id'] ?? null;
        \assert(\is_string($id));

        $response = $client->request('POST', '/api/products/'.$id.'/duplicate', [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode(['sku' => 'TST-200-CLONE'], JSON_THROW_ON_ERROR),
        ]);

        self::assertResponseStatusCodeSame(201);
        $body = $response->toArray();
        self::assertSame('TST-200-CLONE', $body['code'] ?? null);
    }

    #[Test]
    public function postDuplicateAllocatesNextCounterOnSecondRun(): void
    {
        $client = $this->authenticatedClient();
        $created = $client->request('POST', '/api/products', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'TST-300',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ])->toArray();

        $id = $created['id'] ?? null;
        \assert(\is_string($id));

        $first = $client->request('POST', '/api/products/'.$id.'/duplicate', [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([], JSON_THROW_ON_ERROR),
        ])->toArray();
        self::assertSame('TST-300-COPY-1', $first['code'] ?? null);

        $second = $client->request('POST', '/api/products/'.$id.'/duplicate', [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([], JSON_THROW_ON_ERROR),
        ])->toArray();
        self::assertSame('TST-300-COPY-2', $second['code'] ?? null);
    }

    #[Test]
    public function postDuplicateWithCollidingExplicitSkuReturns409(): void
    {
        $client = $this->authenticatedClient();
        $a = $client->request('POST', '/api/products', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'TST-400',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ])->toArray();
        $client->request('POST', '/api/products', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'TST-401',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ]);

        $aId = $a['id'] ?? null;
        \assert(\is_string($aId));

        $client->request('POST', '/api/products/'.$aId.'/duplicate', [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode(['sku' => 'TST-401'], JSON_THROW_ON_ERROR),
        ]);

        self::assertResponseStatusCodeSame(409);
    }

    #[Test]
    public function postDuplicateOnCategoryReturns404(): void
    {
        $client = $this->authenticatedClient();
        $cat = $client->request('POST', '/api/categories', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'CAT-500',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Category),
                'categoryTargetObjectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ])->toArray();

        $catId = $cat['id'] ?? null;
        \assert(\is_string($catId));

        // Category UUID hit through the products duplicate endpoint —
        // controller narrows to kind=product and returns 404.
        $client->request('POST', '/api/products/'.$catId.'/duplicate', [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([], JSON_THROW_ON_ERROR),
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function postDuplicateOnUnknownProductReturns404(): void
    {
        $client = $this->authenticatedClient();
        $client->request('POST', '/api/products/00000000-0000-0000-0000-000000000000/duplicate', [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([], JSON_THROW_ON_ERROR),
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function postDuplicateUnauthenticatedReturns401(): void
    {
        $client = $this->authenticatedClient();
        $created = $client->request('POST', '/api/products', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'TST-AUTH',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ])->toArray();
        $id = $created['id'] ?? null;
        \assert(\is_string($id));

        // Fresh, unauthenticated client.
        static::createClient()->request('POST', '/api/products/'.$id.'/duplicate', [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([], JSON_THROW_ON_ERROR),
        ]);

        self::assertResponseStatusCodeSame(401);
    }
}
