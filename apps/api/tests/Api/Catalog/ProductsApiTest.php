<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use App\Catalog\Domain\ObjectKind;
use PHPUnit\Framework\Attributes\Test;

use const JSON_THROW_ON_ERROR;

/**
 * Happy-path CRUD coverage for `/api/products` sugar path on
 * {@see \App\Catalog\Domain\Entity\CatalogObject} (kind=product).
 *
 * Each test mints its own JWT for a tenant-scoped super_admin user
 * via `authenticatedClient()` so RBAC + tenant filter exercise their
 * real production paths.
 */
final class ProductsApiTest extends CatalogApiTestCase
{
    #[Test]
    public function postCreatesProductRow(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('POST', '/api/products', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'SKU-001',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ]);

        self::assertResponseStatusCodeSame(201);
        $body = $response->toArray();
        self::assertSame('SKU-001', $body['code'] ?? null);
        self::assertSame('product', $body['kind'] ?? null);
    }

    #[Test]
    public function postWithMismatchedKindReturns422(): void
    {
        $client = $this->authenticatedClient();
        // category ObjectType used on /api/products endpoint.
        $client->request('POST', '/api/products', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'SKU-MISMATCH',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Category),
            ], JSON_THROW_ON_ERROR),
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function getCollectionIsScopedToProductKind(): void
    {
        $client = $this->authenticatedClient();
        // Seed: one product, one category.
        $client->request('POST', '/api/products', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'SKU-100',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ]);
        $client->request('POST', '/api/categories', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'CAT-100',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Category),
            ], JSON_THROW_ON_ERROR),
        ]);

        $response = $client->request('GET', '/api/products');
        self::assertResponseIsSuccessful();
        $body = $response->toArray();
        self::assertGreaterThanOrEqual(1, $body['totalItems'] ?? 0);
        $members = $body['member'] ?? $body['hydra:member'] ?? null;
        \assert(\is_array($members));
        foreach ($members as $row) {
            \assert(\is_array($row));
            self::assertSame('product', $row['kind'] ?? null);
        }
    }

    #[Test]
    public function patchUpdatesEnabledFlag(): void
    {
        $client = $this->authenticatedClient();
        $created = $client->request('POST', '/api/products', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'SKU-PATCH',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ])->toArray();

        $id = $created['id'] ?? null;
        \assert(\is_string($id));

        $response = $client->request('PATCH', '/api/products/'.$id, [
            'headers' => ['content-type' => 'application/merge-patch+json'],
            'body' => json_encode(['enabled' => false], JSON_THROW_ON_ERROR),
        ]);

        self::assertResponseIsSuccessful();
        $body = $response->toArray();
        self::assertFalse($body['enabled'] ?? true);
    }

    #[Test]
    public function deleteReturns204(): void
    {
        $client = $this->authenticatedClient();
        $created = $client->request('POST', '/api/products', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'SKU-DELETE',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ])->toArray();

        $id = $created['id'] ?? null;
        \assert(\is_string($id));

        $client->request('DELETE', '/api/products/'.$id);
        self::assertResponseStatusCodeSame(204);

        $client->request('GET', '/api/products/'.$id);
        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function unauthenticatedRequestReturns401(): void
    {
        static::createClient()->request('GET', '/api/products');
        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function getCategoryViaProductsPathReturns404(): void
    {
        $client = $this->authenticatedClient();
        $created = $client->request('POST', '/api/categories', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'CAT-CROSS',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Category),
            ], JSON_THROW_ON_ERROR),
        ])->toArray();
        $id = $created['id'] ?? null;
        \assert(\is_string($id));

        // Same UUID, but accessed via products sugar path → KindItemExtension
        // narrows the query to kind=product, so the row hides → 404.
        $client->request('GET', '/api/products/'.$id);
        self::assertResponseStatusCodeSame(404);
    }
}
