<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Uid\Uuid;

/**
 * CHC-04 (#1288) — `POST /api/products/{id}/schema-drift/acknowledge`.
 */
final class SchemaDriftAcknowledgeApiTest extends CatalogApiTestCase
{
    #[Test]
    public function acknowledgeClearsDriftFlag(): void
    {
        $client = $this->authenticatedClient();
        $productId = $this->makeDriftedProduct();

        $before = $client->request('GET', "/api/products/{$productId}", [
            'headers' => ['accept' => 'application/json'],
        ]);
        self::assertTrue($before->toArray()['schemaDrift'] ?? null);

        $ack = $client->request('POST', "/api/products/{$productId}/schema-drift/acknowledge", [
            'headers' => ['accept' => 'application/json'],
        ]);
        self::assertSame(200, $ack->getStatusCode());
        self::assertFalse($ack->toArray()['schemaDrift'] ?? null);

        $after = $client->request('GET', "/api/products/{$productId}", [
            'headers' => ['accept' => 'application/json'],
        ]);
        self::assertFalse($after->toArray()['schemaDrift'] ?? null);
    }

    #[Test]
    public function unknownObjectReturns404(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('POST', '/api/products/0192ffff-ffff-7fff-8fff-ffffffffffff/schema-drift/acknowledge', [
            'headers' => ['accept' => 'application/json'],
        ]);
        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function unauthenticatedReturns401(): void
    {
        $client = static::createClient();
        $response = $client->request('POST', '/api/products/0192ffff-ffff-7fff-8fff-ffffffffffff/schema-drift/acknowledge', [
            'headers' => ['accept' => 'application/json'],
        ]);
        self::assertSame(401, $response->getStatusCode());
    }

    private function makeDriftedProduct(): string
    {
        $em = $this->em();
        $tenant = $em->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        self::getContainer()->get(TenantContext::class)->set($tenant);

        $productType = $em->find(ObjectType::class, Uuid::fromString($this->objectTypeIdFor(ObjectKind::Product)));
        \assert($productType instanceof ObjectType);

        $product = new CatalogObject($productType, 'SKU-DRIFT');
        $product->recordSchemaSnapshot(['attributeGroupIds' => [], 'capturedAt' => 'x', 'masterCategoryId' => null]);
        $product->flagSchemaDrift(true);
        $em->persist($product);
        $em->flush();

        return $product->getId()->toRfc4122();
    }
}
