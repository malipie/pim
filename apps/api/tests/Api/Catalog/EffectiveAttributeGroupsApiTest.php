<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use App\Catalog\Application\BuiltInSystemAttributesSeeder;
use App\Catalog\Application\ObjectTypeService;
use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\AttributeRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Catalog\Presentation\Controller\ProductReadEndpointsController;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;

use const JSON_THROW_ON_ERROR;

/**
 * VIEW-07.1 (#421+) — `GET /api/products/{id}/effective-attribute-groups`
 * synthetic "default" group coverage. Verifies that ObjectType-attached
 * attributes which are not declared in any AttributeGroup are still
 * surfaced to the form-renderer in a sentinel-id bucket the frontend
 * recognises.
 */
final class EffectiveAttributeGroupsApiTest extends CatalogApiTestCase
{
    #[Test]
    public function exposesLooseObjectTypeAttributesInDefaultGroup(): void
    {
        $client = $this->authenticatedClient();
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);

        $productType = self::getContainer()
            ->get(ObjectTypeRepositoryInterface::class)
            ->findBuiltInByKind(ObjectKind::Product, $tenant);
        \assert(null !== $productType);

        $tenantContext = self::getContainer()->get(TenantContext::class);
        $tenantContext->set($tenant);

        // Attach two loose attributes (no AttributeGroup membership) to
        // the built-in product ObjectType.
        $brandAttr = new Attribute('brand_view07', ['pl' => 'Marka', 'en' => 'Brand'], AttributeType::Text);
        $weightAttr = new Attribute('weight_view07', ['pl' => 'Waga', 'en' => 'Weight'], AttributeType::Number);
        $em = $this->em();
        $em->persist($brandAttr);
        $em->persist($weightAttr);
        $em->flush();

        $service = self::getContainer()->get(ObjectTypeService::class);
        $service->assignAttribute($productType, $brandAttr, required: false, sortOrder: 0);
        $service->assignAttribute($productType, $weightAttr, required: false, sortOrder: 1);

        $tenantContext->clear();

        // Create a product so the endpoint has a concrete subject.
        $created = $client->request('POST', '/api/products', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'V071-LOOSE',
                'objectTypeId' => $productType->getId()->toRfc4122(),
            ], JSON_THROW_ON_ERROR),
        ])->toArray();
        $id = $created['id'] ?? null;
        \assert(\is_string($id));

        $response = $client->request('GET', '/api/products/'.$id.'/effective-attribute-groups');
        self::assertResponseIsSuccessful();
        $body = $response->toArray();

        $groups = $body['groups'] ?? [];
        \assert(\is_array($groups));
        $defaultGroup = null;
        foreach ($groups as $group) {
            \assert(\is_array($group));
            if (ProductReadEndpointsController::SYNTHETIC_DEFAULT_GROUP_ID === ($group['id'] ?? null)) {
                $defaultGroup = $group;
                break;
            }
        }

        self::assertNotNull($defaultGroup, 'Synthetic default group missing from response.');
        self::assertSame('default', $defaultGroup['code'] ?? null);
        self::assertTrue($defaultGroup['is_synthetic'] ?? false);
        self::assertFalse($defaultGroup['is_system_group'] ?? true);

        $defaultAttributes = $defaultGroup['attributes'] ?? [];
        \assert(\is_array($defaultAttributes));
        $codes = array_map(
            static function ($attr): ?string {
                \assert(\is_array($attr));
                $code = $attr['code'] ?? null;

                return \is_string($code) ? $code : null;
            },
            $defaultAttributes,
        );

        self::assertContains('brand_view07', $codes);
        self::assertContains('weight_view07', $codes);
    }

    #[Test]
    public function omitsDefaultGroupWhenEveryAttributeBelongsToAGroup(): void
    {
        $client = $this->authenticatedClient();
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);

        $productType = self::getContainer()
            ->get(ObjectTypeRepositoryInterface::class)
            ->findBuiltInByKind(ObjectKind::Product, $tenant);
        \assert(null !== $productType);

        $created = $client->request('POST', '/api/products', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'V071-CLEAN',
                'objectTypeId' => $productType->getId()->toRfc4122(),
            ], JSON_THROW_ON_ERROR),
        ])->toArray();
        $id = $created['id'] ?? null;
        \assert(\is_string($id));

        $response = $client->request('GET', '/api/products/'.$id.'/effective-attribute-groups');
        self::assertResponseIsSuccessful();
        $body = $response->toArray();

        $groups = $body['groups'] ?? [];
        \assert(\is_array($groups));
        foreach ($groups as $group) {
            \assert(\is_array($group));
            self::assertNotSame(
                ProductReadEndpointsController::SYNTHETIC_DEFAULT_GROUP_ID,
                $group['id'] ?? null,
                'Synthetic default group should be absent when no loose attributes are attached.',
            );
        }
    }

    #[Test]
    public function looseAttributeAlsoMemberOfRealGroupIsNotDuplicated(): void
    {
        $client = $this->authenticatedClient();
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);

        $productType = self::getContainer()
            ->get(ObjectTypeRepositoryInterface::class)
            ->findBuiltInByKind(ObjectKind::Product, $tenant);
        \assert(null !== $productType);

        // The audit AttributeGroup is auto-attached to product by the
        // built-in seeder. Its `created_at` attribute is also attached
        // through ObjectTypeAttribute on the same ObjectType — that is
        // the realistic shape today. The endpoint must surface the
        // attribute exactly once (under the audit group, not in the
        // synthetic default bucket).
        self::getContainer()->get(BuiltInSystemAttributesSeeder::class)->seed($tenant);
        $createdAt = self::getContainer()
            ->get(AttributeRepositoryInterface::class)
            ->findByCode('created_at', $tenant);
        \assert(null !== $createdAt);

        $tenantContext = self::getContainer()->get(TenantContext::class);
        $tenantContext->set($tenant);
        $service = self::getContainer()->get(ObjectTypeService::class);
        $service->assignAttribute($productType, $createdAt, required: false, sortOrder: 99);
        $tenantContext->clear();

        $created = $client->request('POST', '/api/products', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'V071-DEDUP',
                'objectTypeId' => $productType->getId()->toRfc4122(),
            ], JSON_THROW_ON_ERROR),
        ])->toArray();
        $id = $created['id'] ?? null;
        \assert(\is_string($id));

        $response = $client->request('GET', '/api/products/'.$id.'/effective-attribute-groups');
        self::assertResponseIsSuccessful();
        $body = $response->toArray();

        $occurrences = 0;
        $bodyGroups = $body['groups'] ?? [];
        \assert(\is_array($bodyGroups));
        foreach ($bodyGroups as $group) {
            \assert(\is_array($group));
            $attributes = $group['attributes'] ?? [];
            \assert(\is_array($attributes));
            foreach ($attributes as $attr) {
                \assert(\is_array($attr));
                if ('created_at' === ($attr['code'] ?? null)) {
                    ++$occurrences;
                }
            }
        }

        self::assertSame(1, $occurrences, 'Attribute exposed in a real group must not also appear in the synthetic default group.');
    }
}
