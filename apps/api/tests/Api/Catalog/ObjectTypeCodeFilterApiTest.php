<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;

/**
 * #1012 — `?code=` exact-match filter on `/api/object_types`.
 *
 * Without this filter the universal `ObjectListPage` slug resolver
 * received every ObjectType from the GetCollection and silently picked
 * `members[0]` for every slug — navigating `/objects/samochody` rendered
 * the alphabetically-first kind (`product`).
 */
final class ObjectTypeCodeFilterApiTest extends CatalogApiTestCase
{
    #[Test]
    public function codeFilterReturnsExactMatchOnly(): void
    {
        $this->seedCustomObjectType('cars');

        $client = $this->authenticatedClient();
        $response = $client->request('GET', '/api/object_types?code=cars&itemsPerPage=10');

        self::assertSame(200, $response->getStatusCode());
        $payload = $response->toArray();

        self::assertSame(1, $payload['totalItems']);
        $members = $payload['member'] ?? $payload['hydra:member'] ?? [];
        self::assertIsArray($members);
        self::assertCount(1, $members);
        $first = $members[0];
        self::assertIsArray($first);
        self::assertSame('cars', $first['code']);
    }

    #[Test]
    public function codeFilterReturnsEmptyCollectionForUnknownSlug(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('GET', '/api/object_types?code=does-not-exist&itemsPerPage=10');

        self::assertSame(200, $response->getStatusCode());
        $payload = $response->toArray();

        self::assertSame(0, $payload['totalItems']);
    }

    #[Test]
    public function noFilterStillReturnsTheFullCollection(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('GET', '/api/object_types?itemsPerPage=20');

        self::assertSame(200, $response->getStatusCode());
        $payload = $response->toArray();

        // BuiltInObjectTypeSeeder gives every tenant product+category+asset;
        // RbacMatrix tests may add more. We just assert > 1 so the absence
        // of the `?code=` param does NOT mask the filter.
        self::assertGreaterThan(1, $payload['totalItems']);
    }

    private function seedCustomObjectType(string $code): ObjectType
    {
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);

        $tenantContext = self::getContainer()->get(TenantContext::class);
        $tenantContext->set($tenant);

        $type = new ObjectType($code, ObjectKind::Custom, ['pl' => ucfirst($code), 'en' => ucfirst($code)]);
        $em = $this->em();
        $em->persist($type);
        $em->flush();

        $tenantContext->clear();

        return $type;
    }
}
