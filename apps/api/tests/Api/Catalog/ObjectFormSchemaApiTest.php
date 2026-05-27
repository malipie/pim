<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\AttributeGroup;
use App\Catalog\Domain\Entity\AttributeGroupAttribute;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectTypeAttributeGroup;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Uid\Uuid;

/**
 * UI-08.4 (#259) — `GET /api/objects/{id}/form-schema` smoke.
 */
final class ObjectFormSchemaApiTest extends CatalogApiTestCase
{
    #[Test]
    public function getFormSchemaForBuiltInProductReturnsNoGroupsByDefault(): void
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
        self::assertSame([], $groups);
    }

    #[Test]
    public function getFormSchemaExposesDisplayModePerGroup(): void
    {
        $product = $this->seedProduct('SKU-FS-DM-001');
        $this->attachGroupToProductType('display-mode', 'Display Mode');
        $client = $this->authenticatedClient();

        $response = $client->request('GET', '/api/objects/'.$product->getId()->toRfc4122().'/form-schema');

        self::assertSame(200, $response->getStatusCode());
        $payload = $response->toArray();
        $groups = $payload['effectiveGroups'];
        self::assertIsArray($groups);
        self::assertNotEmpty($groups);
        foreach ($groups as $group) {
            self::assertIsArray($group);
            self::assertArrayHasKey('display_mode', $group);
            self::assertContains($group['display_mode'], ['tab', 'stacked']);
        }
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

        $tenantContext = self::getContainer()->get(TenantContext::class);
        $tenantContext->set($tenant);

        $type = self::getContainer()->get(ObjectTypeRepositoryInterface::class)
            ->findBuiltInByKind(ObjectKind::Product, $tenant);
        \assert(null !== $type);

        $product = new CatalogObject($type, $code);
        $em = $this->em();
        $em->persist($product);
        $em->flush();

        $tenantContext->clear();

        return $product;
    }

    private function attachGroupToProductType(string $code, string $label): void
    {
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert(null !== $tenant);

        $tenantContext = self::getContainer()->get(TenantContext::class);
        $tenantContext->set($tenant);

        $type = self::getContainer()->get(ObjectTypeRepositoryInterface::class)
            ->findBuiltInByKind(ObjectKind::Product, $tenant);
        \assert(null !== $type);

        $group = new AttributeGroup($code, ['en' => $label]);
        $attribute = new Attribute($code.'_field', ['en' => $label.' field'], AttributeType::Text);
        $em = $this->em();
        $em->persist($group);
        $em->persist($attribute);
        $em->flush();
        $em->persist(new AttributeGroupAttribute($group, $attribute, 1));
        $em->persist(new ObjectTypeAttributeGroup($type, $group, 1));
        $em->flush();

        $tenantContext->clear();
    }
}
