<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use App\Catalog\Domain\ObjectKind;
use PHPUnit\Framework\Attributes\Test;

use const JSON_THROW_ON_ERROR;

/**
 * VIEW-04 (#408) — coverage for `PATCH /api/categories/{id}/move`.
 */
final class CategoryMoveApiTest extends CatalogApiTestCase
{
    #[Test]
    public function moveRewritesSubtreePath(): void
    {
        $client = $this->authenticatedClient();
        $rootA = $this->createCategory($client, 'root_a');
        $rootB = $this->createCategory($client, 'root_b');
        $child = $this->createCategoryUnder($client, 'child', $rootA);

        $response = $client->request('PATCH', "/api/categories/{$child}/move", [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode(['newParentId' => $rootB], JSON_THROW_ON_ERROR),
        ]);

        self::assertResponseStatusCodeSame(200);
        $body = $response->toArray();
        self::assertSame('root_b.child', $body['newPath'] ?? null);
        self::assertSame(0, $body['affectedDescendants'] ?? null);
    }

    #[Test]
    public function moveToOwnDescendantReturns422(): void
    {
        $client = $this->authenticatedClient();
        $root = $this->createCategory($client, 'cycle_root');
        $child = $this->createCategoryUnder($client, 'cycle_child', $root);

        $client->request('PATCH', "/api/categories/{$root}/move", [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode(['newParentId' => $child], JSON_THROW_ON_ERROR),
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function moveToRootClearsParent(): void
    {
        $client = $this->authenticatedClient();
        $root = $this->createCategory($client, 'flat_root');
        $child = $this->createCategoryUnder($client, 'flat_child', $root);

        $response = $client->request('PATCH', "/api/categories/{$child}/move", [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode(['newParentId' => null], JSON_THROW_ON_ERROR),
        ]);

        self::assertResponseStatusCodeSame(200);
        self::assertSame('flat_child', $response->toArray()['newPath'] ?? null);
    }

    #[Test]
    public function moveUnauthorizedReturns401(): void
    {
        $client = static::createClient();
        $client->request('PATCH', '/api/categories/00000000-0000-0000-0000-000000000000/move', [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode(['newParentId' => null], JSON_THROW_ON_ERROR),
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    private function createCategory(\ApiPlatform\Symfony\Bundle\Test\Client $client, string $code): string
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

    private function createCategoryUnder(\ApiPlatform\Symfony\Bundle\Test\Client $client, string $code, string $parentId): string
    {
        $response = $client->request('POST', '/api/categories', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => $code,
                'parentId' => $parentId,
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Category),
            ], JSON_THROW_ON_ERROR),
        ]);
        $id = $response->toArray()['id'] ?? null;
        \assert(\is_string($id));

        return $id;
    }
}
