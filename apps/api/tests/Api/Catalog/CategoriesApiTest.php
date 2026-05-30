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
                'categoryTargetObjectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
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

    #[Test]
    public function postRootCategorySetsPathToCode(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('POST', '/api/categories', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'medical',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Category),
                'categoryTargetObjectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ]);

        self::assertResponseStatusCodeSame(201);
        $body = $response->toArray();
        self::assertSame('medical', $body['path'] ?? null);
    }

    #[Test]
    public function postChildCategoryBuildsLtreePathFromParent(): void
    {
        $client = $this->authenticatedClient();
        $rootResponse = $client->request('POST', '/api/categories', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'lekarz',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Category),
                'categoryTargetObjectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(201);
        $rootId = $rootResponse->toArray()['id'] ?? null;
        \assert(\is_string($rootId));

        $childResponse = $client->request('POST', '/api/categories', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'chirurg',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Category),
                'categoryTargetObjectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
                'parentId' => $rootId,
            ], JSON_THROW_ON_ERROR),
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('lekarz.chirurg', $childResponse->toArray()['path'] ?? null);
    }

    #[Test]
    public function getUsageReturnsCounts(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('POST', '/api/categories', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'usage_target',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Category),
                'categoryTargetObjectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(201);
        $id = $response->toArray()['id'] ?? null;
        \assert(\is_string($id));

        $usage = $client->request('GET', "/api/categories/{$id}/usage");
        self::assertResponseStatusCodeSame(200);

        $body = $usage->toArray();
        self::assertSame(0, $body['instanceCount'] ?? null);
        self::assertSame(0, $body['descendantCount'] ?? null);
        self::assertSame([], $body['declaredFor'] ?? null);
    }
}
