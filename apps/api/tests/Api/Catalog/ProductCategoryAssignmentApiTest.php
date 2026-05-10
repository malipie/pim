<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Catalog\Domain\ObjectKind;
use PHPUnit\Framework\Attributes\Test;

use const JSON_THROW_ON_ERROR;

/**
 * PCAT-02 (#475) — coverage for the four product↔category assignment
 * routes (list/replace/add/detach) on /api/products/{id}/categories.
 */
final class ProductCategoryAssignmentApiTest extends CatalogApiTestCase
{
    #[Test]
    public function getReturnsEmptyForFreshProduct(): void
    {
        $client = $this->authenticatedClient();
        $productId = $this->createProduct($client, 'sku_empty');

        $response = $client->request('GET', "/api/products/{$productId}/categories");
        self::assertResponseStatusCodeSame(200);

        $body = $response->toArray();
        self::assertSame($productId, $body['productId'] ?? null);
        self::assertArrayHasKey('primaryCategoryId', $body);
        self::assertNull($body['primaryCategoryId']);
        self::assertSame([], $body['assignments'] ?? null);
    }

    #[Test]
    public function putReplaceSetsAssignmentsAndPrimary(): void
    {
        $client = $this->authenticatedClient();
        $productId = $this->createProduct($client, 'sku_put');
        $cat1 = $this->createCategory($client, 'put_cat_a');
        $cat2 = $this->createCategory($client, 'put_cat_b');

        $response = $client->request('PUT', "/api/products/{$productId}/categories", [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'categoryIds' => [$cat1, $cat2],
                'primaryCategoryId' => $cat2,
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(200);

        $body = $response->toArray();
        self::assertSame($cat2, $body['primaryCategoryId'] ?? null);
        $assignments = $body['assignments'] ?? null;
        \assert(\is_array($assignments));
        self::assertCount(2, $assignments);
        $first = $assignments[0];
        $second = $assignments[1];
        \assert(\is_array($first));
        \assert(\is_array($second));
        self::assertSame($cat1, $first['categoryId'] ?? null);
        self::assertFalse($first['isPrimary'] ?? null);
        self::assertSame($cat2, $second['categoryId'] ?? null);
        self::assertTrue($second['isPrimary'] ?? null);
    }

    #[Test]
    public function putValidates422WhenPrimaryNotInList(): void
    {
        $client = $this->authenticatedClient();
        $productId = $this->createProduct($client, 'sku_pnotin');
        $cat1 = $this->createCategory($client, 'pnotin_cat_a');
        $cat2 = $this->createCategory($client, 'pnotin_cat_b');

        $client->request('PUT', "/api/products/{$productId}/categories", [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'categoryIds' => [$cat1],
                'primaryCategoryId' => $cat2,
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function putValidates422OnDuplicateIds(): void
    {
        $client = $this->authenticatedClient();
        $productId = $this->createProduct($client, 'sku_dup');
        $cat = $this->createCategory($client, 'dup_cat');

        $client->request('PUT', "/api/products/{$productId}/categories", [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'categoryIds' => [$cat, $cat],
                'primaryCategoryId' => $cat,
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function putValidates422OnNonCategoryTarget(): void
    {
        $client = $this->authenticatedClient();
        $productId = $this->createProduct($client, 'sku_kindcheck');
        // A second product passed in `categoryIds` should be rejected because
        // it is `kind=product`, not `kind=category`.
        $otherProduct = $this->createProduct($client, 'sku_other');

        $client->request('PUT', "/api/products/{$productId}/categories", [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'categoryIds' => [$otherProduct],
                'primaryCategoryId' => $otherProduct,
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function putValidates422OverMaxCount(): void
    {
        $client = $this->authenticatedClient();
        $productId = $this->createProduct($client, 'sku_overcap');
        $tooMany = [];
        for ($i = 0; $i < 51; ++$i) {
            // Use a syntactically valid UUID — the cap check fires before
            // any per-row category lookup so we don't need real categories.
            $tooMany[] = sprintf('00000000-0000-7000-8000-%012d', $i);
        }

        $client->request('PUT', "/api/products/{$productId}/categories", [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'categoryIds' => $tooMany,
                'primaryCategoryId' => $tooMany[0],
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function postSingleAddIsIdempotent(): void
    {
        $client = $this->authenticatedClient();
        $productId = $this->createProduct($client, 'sku_postidem');
        $cat = $this->createCategory($client, 'postidem_cat');

        $client->request('POST', "/api/products/{$productId}/categories", [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode(['categoryId' => $cat, 'isPrimary' => false], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(201);

        $client->request('POST', "/api/products/{$productId}/categories", [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode(['categoryId' => $cat, 'isPrimary' => false], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(200);
    }

    #[Test]
    public function postSetsPrimaryDemotesPrevious(): void
    {
        $client = $this->authenticatedClient();
        $productId = $this->createProduct($client, 'sku_demote');
        $cat1 = $this->createCategory($client, 'demote_a');
        $cat2 = $this->createCategory($client, 'demote_b');

        // Establish first as primary
        $client->request('PUT', "/api/products/{$productId}/categories", [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'categoryIds' => [$cat1],
                'primaryCategoryId' => $cat1,
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(200);

        // Add second as the new primary — old primary must be demoted
        $client->request('POST', "/api/products/{$productId}/categories", [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode(['categoryId' => $cat2, 'isPrimary' => true], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(201);

        $list = $client->request('GET', "/api/products/{$productId}/categories")->toArray();
        self::assertSame($cat2, $list['primaryCategoryId'] ?? null);
        $assignments = $list['assignments'] ?? null;
        \assert(\is_array($assignments));
        self::assertCount(2, $assignments);
        $primaryCount = 0;
        foreach ($assignments as $row) {
            \assert(\is_array($row));
            if (true === ($row['isPrimary'] ?? null)) {
                ++$primaryCount;
            }
        }
        self::assertSame(1, $primaryCount);
    }

    #[Test]
    public function deleteRemovesAssignmentAndPromotesNext(): void
    {
        $client = $this->authenticatedClient();
        $productId = $this->createProduct($client, 'sku_promote');
        $cat1 = $this->createCategory($client, 'promote_a');
        $cat2 = $this->createCategory($client, 'promote_b');

        $client->request('PUT', "/api/products/{$productId}/categories", [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'categoryIds' => [$cat1, $cat2],
                'primaryCategoryId' => $cat1,
            ], JSON_THROW_ON_ERROR),
        ]);

        $client->request('DELETE', "/api/products/{$productId}/categories/{$cat1}");
        self::assertResponseStatusCodeSame(204);

        $list = $client->request('GET', "/api/products/{$productId}/categories")->toArray();
        self::assertSame($cat2, $list['primaryCategoryId'] ?? null);
        $remaining = $list['assignments'] ?? [];
        \assert(\is_array($remaining));
        self::assertCount(1, $remaining);
    }

    #[Test]
    public function deleteLastPrimaryLeavesProductWithNoPrimary(): void
    {
        $client = $this->authenticatedClient();
        $productId = $this->createProduct($client, 'sku_lastp');
        $cat = $this->createCategory($client, 'lastp_cat');

        $client->request('PUT', "/api/products/{$productId}/categories", [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'categoryIds' => [$cat],
                'primaryCategoryId' => $cat,
            ], JSON_THROW_ON_ERROR),
        ]);

        $client->request('DELETE', "/api/products/{$productId}/categories/{$cat}");
        self::assertResponseStatusCodeSame(204);

        $list = $client->request('GET', "/api/products/{$productId}/categories")->toArray();
        self::assertArrayHasKey('primaryCategoryId', $list);
        self::assertNull($list['primaryCategoryId']);
        self::assertSame([], $list['assignments'] ?? null);
    }

    #[Test]
    public function unauthorizedReturns401(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/products/00000000-0000-7000-8000-000000000000/categories');
        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function listOnNonProductReturns404(): void
    {
        $client = $this->authenticatedClient();
        // A category id passed as the {id} segment of /products/{id}/...
        // must yield 404, not leak the row.
        $categoryId = $this->createCategory($client, 'looks_like_product');

        $client->request('GET', "/api/products/{$categoryId}/categories");
        self::assertResponseStatusCodeSame(404);
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
            ], JSON_THROW_ON_ERROR),
        ]);
        $id = $response->toArray()['id'] ?? null;
        \assert(\is_string($id));

        return $id;
    }
}
