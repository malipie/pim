<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use App\Catalog\Application\BuiltInSystemAttributesSeeder;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectTypeAttributeGroup;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\AttributeGroupRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

/**
 * MODR-01 (#923) — `PATCH /api/object_types/{id}/groups/{groupId}` updates
 * the per-assignment `display_mode` on the junction.
 */
final class ObjectTypeAttributeGroupDisplayModePatchApiTest extends CatalogApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert(null !== $tenant);
        self::getContainer()->get(BuiltInSystemAttributesSeeder::class)->seed($tenant);
    }

    #[Test]
    public function patchChangesDisplayModeToStacked(): void
    {
        $productId = $this->objectTypeIdFor(ObjectKind::Product);
        $auditGroupId = $this->seededAuditGroupId();

        $client = $this->authenticatedClient();

        $client->request('PATCH', '/api/object_types/'.$productId.'/groups/'.$auditGroupId, [
            'json' => ['display_mode' => 'stacked'],
            'headers' => ['content-type' => 'application/json'],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        // Form-schema for a product of this type must reflect the new mode.
        $product = $this->seedProduct('SKU-MODR01-001');
        $response = $client->request(
            'GET',
            '/api/objects/'.$product->getId()->toRfc4122().'/form-schema'
        );
        self::assertResponseIsSuccessful();

        $payload = $response->toArray();
        $groups = $payload['effectiveGroups'];
        self::assertIsArray($groups);
        $audit = null;
        foreach ($groups as $group) {
            \assert(\is_array($group));
            if (($group['code'] ?? null) === 'audit') {
                $audit = $group;
                break;
            }
        }
        self::assertIsArray($audit);
        self::assertSame('stacked', $audit['display_mode']);
    }

    #[Test]
    public function patchRejectsInvalidDisplayMode(): void
    {
        $productId = $this->objectTypeIdFor(ObjectKind::Product);
        $auditGroupId = $this->seededAuditGroupId();

        $client = $this->authenticatedClient();
        $client->request('PATCH', '/api/object_types/'.$productId.'/groups/'.$auditGroupId, [
            'json' => ['display_mode' => 'accordion'],
            'headers' => ['content-type' => 'application/json'],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    #[Test]
    public function patchReturnsNotFoundForMissingAssignment(): void
    {
        $productId = $this->objectTypeIdFor(ObjectKind::Product);
        $unknownGroupId = Uuid::v7()->toRfc4122();

        $client = $this->authenticatedClient();
        $client->request('PATCH', '/api/object_types/'.$productId.'/groups/'.$unknownGroupId, [
            'json' => ['display_mode' => 'stacked'],
            'headers' => ['content-type' => 'application/json'],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    #[Test]
    public function patchReturnsBadRequestWhenBodyMissingField(): void
    {
        $productId = $this->objectTypeIdFor(ObjectKind::Product);
        $auditGroupId = $this->seededAuditGroupId();

        $client = $this->authenticatedClient();
        $client->request('PATCH', '/api/object_types/'.$productId.'/groups/'.$auditGroupId, [
            'json' => [],
            'headers' => ['content-type' => 'application/json'],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    #[Test]
    public function entityRejectsInvalidDisplayMode(): void
    {
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        $type = self::getContainer()->get(ObjectTypeRepositoryInterface::class)
            ->findBuiltInByKind(ObjectKind::Product, $tenant);
        \assert(null !== $type);
        $group = self::getContainer()->get(AttributeGroupRepositoryInterface::class)
            ->findByCode('audit', $tenant);
        \assert(null !== $group);

        $this->expectException(InvalidArgumentException::class);

        new ObjectTypeAttributeGroup($type, $group, 0, 'invalid_mode');
    }

    private function seededAuditGroupId(): string
    {
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        $group = self::getContainer()->get(AttributeGroupRepositoryInterface::class)
            ->findByCode('audit', $tenant);
        \assert(null !== $group);

        return $group->getId()->toRfc4122();
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
}
