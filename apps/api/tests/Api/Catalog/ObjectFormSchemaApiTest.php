<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use App\Catalog\Application\BuiltInSystemAttributesSeeder;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Uid\Uuid;

/**
 * UI-08.4 (#259) — `GET /api/objects/{id}/form-schema` smoke.
 */
final class ObjectFormSchemaApiTest extends CatalogApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert(null !== $tenant);
        self::getContainer()->get(BuiltInSystemAttributesSeeder::class)->seed($tenant);
    }

    #[Test]
    public function getFormSchemaForBuiltInProductReturnsAuditGroup(): void
    {
        $product = $this->seedProduct('SKU-FS-001');
        $client = $this->authenticatedClient();

        $response = $client->request('GET', '/api/objects/'.$product->getId()->toRfc4122().'/form-schema');

        self::assertSame(200, $response->getStatusCode());
        $payload = $response->toArray();
        self::assertSame($product->getId()->toRfc4122(), $payload['objectId']);
        $type = $payload['objectType'];
        self::assertIsArray($type);
        self::assertSame('product', $type['kind']);
        $groups = $payload['effectiveGroups'];
        self::assertIsArray($groups);
        self::assertCount(1, $groups);
        $audit = $groups[0];
        self::assertIsArray($audit);
        self::assertSame('audit', $audit['code']);
        self::assertTrue($audit['is_system_group']);
        self::assertIsArray($audit['attributes']);
        self::assertCount(4, $audit['attributes']);
    }

    #[Test]
    public function getFormSchemaReturnsNotFoundForUnknownId(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('GET', '/api/objects/'.Uuid::v7()->toRfc4122().'/form-schema');

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function getFormSchemaRequiresAuthentication(): void
    {
        $product = $this->seedProduct('SKU-FS-002');
        $client = static::createClient();

        $response = $client->request('GET', '/api/objects/'.$product->getId()->toRfc4122().'/form-schema');

        self::assertSame(401, $response->getStatusCode());
    }

    private function seedProduct(string $code): CatalogObject
    {
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert(null !== $tenant);

        $type = self::getContainer()->get(ObjectTypeRepositoryInterface::class)
            ->findBuiltInByKind(ObjectKind::Product, $tenant);
        \assert(null !== $type);

        $product = new CatalogObject($type, $code);
        $em = $this->em();
        $em->persist($product);
        $em->flush();

        return $product;
    }
}
