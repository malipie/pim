<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Uid\Uuid;

use const JSON_THROW_ON_ERROR;

/**
 * ULV-05 (#987) — `POST /api/objects/bulk` smoke.
 */
final class BulkObjectsApiTest extends CatalogApiTestCase
{
    #[Test]
    public function rejectsMissingAction(): void
    {
        $client = $this->authenticatedClient();

        $response = $client->request('POST', '/api/objects/bulk', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([], JSON_THROW_ON_ERROR),
        ]);

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function rejectsUnknownAction(): void
    {
        $client = $this->authenticatedClient();

        $response = $client->request('POST', '/api/objects/bulk', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'action' => 'archive',
                'object_ids' => [Uuid::v7()->toRfc4122()],
            ], JSON_THROW_ON_ERROR),
        ]);

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function rejectsMalformedObjectIds(): void
    {
        $client = $this->authenticatedClient();

        $response = $client->request('POST', '/api/objects/bulk', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'action' => 'delete',
                'object_ids' => ['not-a-uuid'],
            ], JSON_THROW_ON_ERROR),
        ]);

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function rejectsExceedingHardCap(): void
    {
        $client = $this->authenticatedClient();

        $ids = [];
        for ($i = 0; $i < 1001; ++$i) {
            $ids[] = Uuid::v7()->toRfc4122();
        }

        $response = $client->request('POST', '/api/objects/bulk', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['action' => 'delete', 'object_ids' => $ids], JSON_THROW_ON_ERROR),
        ]);

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function deletesProductsViaBulkEndpoint(): void
    {
        $product1 = $this->seedProduct('SKU-BULK-001');
        $product2 = $this->seedProduct('SKU-BULK-002');
        $client = $this->authenticatedClient();

        $response = $client->request('POST', '/api/objects/bulk', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'action' => 'delete',
                'object_ids' => [
                    $product1->getId()->toRfc4122(),
                    $product2->getId()->toRfc4122(),
                ],
            ], JSON_THROW_ON_ERROR),
        ]);

        self::assertSame(200, $response->getStatusCode());
        $payload = $response->toArray();
        self::assertSame('delete', $payload['action']);
        self::assertSame(2, $payload['requested']);
        self::assertSame(2, $payload['affected']);

        $this->em()->clear();
        $repo = self::getContainer()->get(CatalogObjectRepositoryInterface::class);
        self::assertNull($repo->findById($product1->getId()));
        self::assertNull($repo->findById($product2->getId()));
    }

    private function seedProduct(string $code): CatalogObject
    {
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);

        $tenantContext = self::getContainer()->get(TenantContext::class);
        $tenantContext->set($tenant);

        $type = self::getContainer()->get(ObjectTypeRepositoryInterface::class)
            ->findBuiltInByKind(ObjectKind::Product, $tenant);
        \assert(null !== $type);

        $object = new CatalogObject($type, $code);
        $object->updateAttributeIndex(['name' => 'Display '.$code]);
        $em = $this->em();
        $em->persist($object);
        $em->flush();

        $tenantContext->clear();

        return $object;
    }

    #[Test]
    public function returnsZeroAffectedForUnknownIds(): void
    {
        $client = $this->authenticatedClient();

        $response = $client->request('POST', '/api/objects/bulk', [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'action' => 'delete',
                'object_ids' => [Uuid::v7()->toRfc4122(), Uuid::v7()->toRfc4122()],
            ], JSON_THROW_ON_ERROR),
        ]);

        self::assertSame(200, $response->getStatusCode());
        $payload = $response->toArray();
        self::assertSame(2, $payload['requested']);
        self::assertSame(0, $payload['affected']);
    }
}
