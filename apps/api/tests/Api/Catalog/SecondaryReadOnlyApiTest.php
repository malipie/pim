<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use Generator;
use PHPUnit\Framework\Attributes\Test;

/**
 * Smoke coverage for the read-only secondary entities exposed in #41.
 *
 * Mutations (POST/PATCH/DELETE) on these resources are deferred to the
 * admin-UI ticket bundle (epic 0.6) — see lessons z 0.4.1 / #41 for the
 * scoping rationale. Each endpoint here only proves: GET 200 + JSON-LD
 * envelope shape + `totalItems` numeric.
 */
final class SecondaryReadOnlyApiTest extends CatalogApiTestCase
{
    public static function readOnlyEndpoints(): Generator
    {
        yield '/api/object_types' => ['/api/object_types'];
        yield '/api/attributes' => ['/api/attributes'];
        yield '/api/attribute_groups' => ['/api/attribute_groups'];
        // /api/associations removed in MOD-02 (#894) — see ObjectRelation;
        // the CRUD surface for relations lands in MOD-06 (#898).
        yield '/api/channels' => ['/api/channels'];
        yield '/api/asset_storage' => ['/api/asset_storage'];
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('readOnlyEndpoints')]
    public function getCollectionRespondsWithHydraEnvelope(string $path): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('GET', $path);

        self::assertResponseIsSuccessful();
        $body = $response->toArray();
        self::assertArrayHasKey('totalItems', $body);
        self::assertIsInt($body['totalItems']);
    }

    #[Test]
    #[\PHPUnit\Framework\Attributes\DataProvider('readOnlyEndpoints')]
    public function unauthenticatedRequestReturns401(string $path): void
    {
        static::createClient()->request('GET', $path);
        self::assertResponseStatusCodeSame(401);
    }
}
