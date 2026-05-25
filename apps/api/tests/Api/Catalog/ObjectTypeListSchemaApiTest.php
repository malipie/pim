<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;

/**
 * ULV-03 (#984) — `GET /api/object_types/{id}/list-schema` smoke.
 */
final class ObjectTypeListSchemaApiTest extends CatalogApiTestCase
{
    #[Test]
    public function listSchemaForBuiltInProductReturnsSystemColumns(): void
    {
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert(null !== $tenant);
        self::getContainer()->get(TenantContext::class)->set($tenant);

        $productType = self::getContainer()->get(ObjectTypeRepositoryInterface::class)
            ->findBuiltInByKind(ObjectKind::Product, $tenant);
        \assert(null !== $productType);

        $client = $this->authenticatedClient();
        $response = $client->request('GET', '/api/object_types/'.$productType->getId()->toRfc4122().'/list-schema');

        self::assertSame(200, $response->getStatusCode());
        $payload = $response->toArray();

        self::assertArrayHasKey('objectType', $payload);
        self::assertArrayHasKey('columns', $payload);
        self::assertArrayHasKey('filterableAttributes', $payload);
        self::assertArrayHasKey('searchableAttributes', $payload);

        $objectTypeRow = $payload['objectType'];
        self::assertIsArray($objectTypeRow);
        self::assertSame('product', $objectTypeRow['kind']);
        self::assertSame('product', $objectTypeRow['code']);
        self::assertArrayHasKey('is_categorizable', $objectTypeRow);
        self::assertArrayHasKey('has_variants', $objectTypeRow);

        // Four mandatory system columns.
        $columns = $payload['columns'];
        self::assertIsArray($columns);
        $systemKeys = array_column(
            array_filter($columns, static fn ($c): bool => \is_array($c) && true === ($c['system'] ?? false)),
            'key',
        );
        self::assertContains('code', $systemKeys);
        self::assertContains('status', $systemKeys);
        self::assertContains('completeness', $systemKeys);
        self::assertContains('updatedAt', $systemKeys);
    }

    #[Test]
    public function listSchemaReturnsNotFoundForUnknownId(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request(
            'GET',
            '/api/object_types/01900000-0000-7000-8000-000000000000/list-schema',
        );

        self::assertSame(404, $response->getStatusCode());
    }
}
