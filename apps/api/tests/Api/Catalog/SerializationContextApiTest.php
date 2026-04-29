<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use App\Catalog\Domain\ObjectKind;
use PHPUnit\Framework\Attributes\Test;

use const JSON_THROW_ON_ERROR;

/**
 * Coverage for #42 (0.4.2) — per-context serialization groups.
 *
 * Three contexts (`admin`, `integration`, `public`) project the same
 * `CatalogObject` row to different shapes. The test pins the key
 * invariants:
 *   - admin (default) shows full editorial state including
 *     `completeness` and `path`;
 *   - integration drops PIM-internal book-keeping (`completeness`,
 *     `path`, `parent`) but keeps state and timestamps;
 *   - public is the read-only catalogue surface (id + code + kind +
 *     attributes_indexed) — no timestamps, no status, no relations
 *     beyond what the `attributesIndexed` cache exposes.
 *   - `tenant` is **never** present in any group; defence-in-depth
 *     against multi-tenant cross-leak.
 */
final class SerializationContextApiTest extends CatalogApiTestCase
{
    #[Test]
    public function adminContextExposesFullCatalogObjectShape(): void
    {
        $client = $this->authenticatedClient();
        $created = $client->request('POST', '/api/products', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'ADMIN-001',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ])->toArray();

        $id = $created['id'] ?? null;
        \assert(\is_string($id));

        $body = $client->request('GET', '/api/products/'.$id)->toArray();

        self::assertSame('ADMIN-001', $body['code'] ?? null);
        self::assertSame('product', $body['kind'] ?? null);
        self::assertArrayHasKey('completeness', $body);
        self::assertArrayHasKey('status', $body);
        self::assertArrayHasKey('attributesIndexed', $body);
        self::assertArrayHasKey('createdAt', $body);
        self::assertArrayNotHasKey('tenant', $body, 'tenant must never leak in any group.');
    }

    #[Test]
    public function integrationContextOmitsInternalBookKeeping(): void
    {
        $client = $this->authenticatedClient();
        $created = $client->request('POST', '/api/products', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'INTEG-001',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ])->toArray();

        $id = $created['id'] ?? null;
        \assert(\is_string($id));

        $body = $client->request('GET', '/api/products/'.$id.'?context=integration')->toArray();

        self::assertSame('INTEG-001', $body['code'] ?? null);
        self::assertSame('product', $body['kind'] ?? null);
        self::assertArrayHasKey('attributesIndexed', $body);
        self::assertArrayHasKey('createdAt', $body);
        self::assertArrayNotHasKey('completeness', $body, 'completeness is admin-only.');
        self::assertArrayNotHasKey('path', $body, 'path is admin-only.');
        self::assertArrayNotHasKey('parent', $body, 'parent is admin-only.');
        self::assertArrayNotHasKey('tenant', $body);
    }

    #[Test]
    public function publicContextIsMinimalCatalogueProjection(): void
    {
        $client = $this->authenticatedClient();
        $created = $client->request('POST', '/api/products', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'PUB-001',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ])->toArray();

        $id = $created['id'] ?? null;
        \assert(\is_string($id));

        $body = $client->request('GET', '/api/products/'.$id.'?context=public')->toArray();

        self::assertSame('PUB-001', $body['code'] ?? null);
        self::assertSame('product', $body['kind'] ?? null);
        self::assertArrayHasKey('attributesIndexed', $body);
        // Public surface does not include status, completeness, timestamps,
        // or the parent relation.
        self::assertArrayNotHasKey('status', $body);
        self::assertArrayNotHasKey('completeness', $body);
        self::assertArrayNotHasKey('createdAt', $body);
        self::assertArrayNotHasKey('updatedAt', $body);
        self::assertArrayNotHasKey('tenant', $body);
    }

    #[Test]
    public function unknownContextValueFallsBackToResourceDefault(): void
    {
        $client = $this->authenticatedClient();
        $created = $client->request('POST', '/api/products', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'BOGUS-001',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ])->toArray();

        $id = $created['id'] ?? null;
        \assert(\is_string($id));

        $body = $client->request('GET', '/api/products/'.$id.'?context=root')->toArray();

        // Unknown scope → resource default (admin:read) — completeness back.
        self::assertArrayHasKey('completeness', $body);
    }
}
