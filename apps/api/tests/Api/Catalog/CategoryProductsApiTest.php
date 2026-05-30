<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Catalog\Domain\ObjectKind;
use PHPUnit\Framework\Attributes\Test;

use const JSON_THROW_ON_ERROR;

/**
 * PCAT-06 (#479) — coverage for `GET /api/categories/{id}/products`.
 */
final class CategoryProductsApiTest extends CatalogApiTestCase
{
    #[Test]
    public function listsAssignedProductsWithPagination(): void
    {
        $client = $this->authenticatedClient();
        $categoryId = $this->createCategory($client, 'cat_list');

        $first = $this->createProduct($client, 'sku_a');
        $second = $this->createProduct($client, 'sku_b');

        $this->assign($client, $first, $categoryId, true);
        $this->assign($client, $second, $categoryId, false);

        $response = $client->request('GET', "/api/categories/{$categoryId}/products");
        self::assertResponseStatusCodeSame(200);

        $body = $response->toArray();
        self::assertSame(2, $body['hydra:totalItems'] ?? null);
        $members = $body['hydra:member'] ?? null;
        \assert(\is_array($members));
        self::assertCount(2, $members);

        // Sanity check: each member carries the assignment-level metadata.
        foreach ($members as $row) {
            \assert(\is_array($row));
            self::assertArrayHasKey('isPrimary', $row);
            self::assertArrayHasKey('code', $row);
        }
    }

    #[Test]
    public function emptyResponseForCategoryWithoutAssignments(): void
    {
        $client = $this->authenticatedClient();
        $categoryId = $this->createCategory($client, 'cat_empty');

        $response = $client->request('GET', "/api/categories/{$categoryId}/products");
        self::assertResponseStatusCodeSame(200);

        $body = $response->toArray();
        self::assertSame(0, $body['hydra:totalItems'] ?? null);
        self::assertSame([], $body['hydra:member'] ?? null);
    }

    #[Test]
    public function respectsItemsPerPageCap(): void
    {
        $client = $this->authenticatedClient();
        $categoryId = $this->createCategory($client, 'cat_cap');

        // Create 3 products, request 1 per page → assert hydra:totalItems=3
        // and members count is 1. The cap (200) is asserted via the
        // controller default; here we exercise the small-page path.
        $a = $this->createProduct($client, 'cap_a');
        $b = $this->createProduct($client, 'cap_b');
        $c = $this->createProduct($client, 'cap_c');
        foreach ([$a, $b, $c] as $p) {
            $this->assign($client, $p, $categoryId, false);
        }

        $response = $client->request(
            'GET',
            "/api/categories/{$categoryId}/products?page=1&itemsPerPage=1",
        );
        self::assertResponseStatusCodeSame(200);

        $body = $response->toArray();
        self::assertSame(3, $body['hydra:totalItems'] ?? null);
        $members = $body['hydra:member'] ?? null;
        \assert(\is_array($members));
        self::assertCount(1, $members);
    }

    #[Test]
    public function nonCategoryIdReturns422(): void
    {
        $client = $this->authenticatedClient();
        $productId = $this->createProduct($client, 'sku_kindcheck');

        $client->request('GET', "/api/categories/{$productId}/products");
        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function unauthorizedReturns401(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/categories/00000000-0000-7000-8000-000000000000/products');
        self::assertResponseStatusCodeSame(401);
    }

    private function createProduct(Client $client, string $code): string
    {
        $response = $client->request('POST', '/api/products', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => $code,
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ]);
        $id = $response->toArray()['id'] ?? null;
        \assert(\is_string($id));

        return $id;
    }

    private function createCategory(Client $client, string $code): string
    {
        $response = $client->request('POST', '/api/categories', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => $code,
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Category),
                'categoryTargetObjectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ]);
        $id = $response->toArray()['id'] ?? null;
        \assert(\is_string($id));

        return $id;
    }

    private function assign(Client $client, string $productId, string $categoryId, bool $primary): void
    {
        $client->request('POST', "/api/products/{$productId}/categories", [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'categoryId' => $categoryId,
                'isPrimary' => $primary,
            ], JSON_THROW_ON_ERROR),
        ]);
    }
}
