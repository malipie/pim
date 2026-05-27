<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use App\Catalog\Application\BuiltInSystemAttributesSeeder;
use App\Catalog\Domain\Entity\AttributeGroup;
use App\Catalog\Domain\Entity\ObjectTypeAttributeGroup;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\AttributeGroupRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;

/**
 * UI-08.5 (#260) — AttributeGroup CRUD ApiResource smoke + invariants.
 */
final class AttributeGroupsApiTest extends CatalogApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        // System attributes are seeded as Attribute rows only. AttributeGroup
        // visibility is explicit modeling configuration.
        self::getContainer()->get(BuiltInSystemAttributesSeeder::class)->seed($tenant);
    }

    #[Test]
    public function postCreatesAttributeGroup(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('POST', '/api/attribute_groups', [
            'json' => [
                'code' => 'marketing',
                'label' => ['en' => 'Marketing', 'pl' => 'Marketing'],
                'description' => ['en' => 'Marketing-related fields'],
                'icon' => 'Megaphone',
                'color' => '#EC4899',
                'position' => 5,
            ],
        ]);

        self::assertSame(201, $response->getStatusCode());
        $payload = $response->toArray();
        self::assertSame('marketing', $payload['code']);
        self::assertSame('Megaphone', $payload['icon']);
        self::assertSame('#EC4899', $payload['color']);
        self::assertFalse($payload['systemGroup']);
    }

    #[Test]
    public function postRejectsDuplicateCode(): void
    {
        $client = $this->authenticatedClient();

        $client->request('POST', '/api/attribute_groups', [
            'json' => ['code' => 'logistics', 'label' => ['en' => 'Logistics']],
        ]);
        $second = $client->request('POST', '/api/attribute_groups', [
            'json' => ['code' => 'logistics', 'label' => ['en' => 'Logistics']],
        ]);

        self::assertSame(409, $second->getStatusCode());
    }

    #[Test]
    public function patchUpdatesLabelAndIcon(): void
    {
        $client = $this->authenticatedClient();

        $created = $client->request('POST', '/api/attribute_groups', [
            'json' => ['code' => 'seo', 'label' => ['en' => 'SEO']],
        ]);
        $id = self::extractId($created->toArray());

        $patch = $client->request('PATCH', '/api/attribute_groups/'.$id, [
            'json' => [
                'label' => ['en' => 'Search Engine Optimisation', 'pl' => 'SEO'],
                'icon' => 'Search',
            ],
            'headers' => ['content-type' => 'application/merge-patch+json'],
        ]);

        self::assertSame(200, $patch->getStatusCode());
        $payload = $patch->toArray();
        self::assertIsArray($payload['label']);
        self::assertSame('Search Engine Optimisation', $payload['label']['en']);
        self::assertSame('Search', $payload['icon']);
    }

    #[Test]
    public function deleteRemovesAnUnattachedGroup(): void
    {
        $client = $this->authenticatedClient();

        $created = $client->request('POST', '/api/attribute_groups', [
            'json' => ['code' => 'temp', 'label' => ['en' => 'Temp']],
        ]);
        $id = self::extractId($created->toArray());

        $delete = $client->request('DELETE', '/api/attribute_groups/'.$id);
        self::assertSame(204, $delete->getStatusCode());

        $get = $client->request('GET', '/api/attribute_groups/'.$id);
        self::assertSame(404, $get->getStatusCode());
    }

    #[Test]
    public function deleteBlocksWhenGroupIsSystemManaged(): void
    {
        $client = $this->authenticatedClient();
        $tenantContext = self::getContainer()->get(TenantContext::class);
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        $tenantContext->set($tenant);
        $systemGroup = new AttributeGroup('core_system', ['en' => 'Core system'], isSystemGroup: true);
        $this->em()->persist($systemGroup);
        $this->em()->flush();
        $tenantContext->clear();

        $delete = $client->request('DELETE', '/api/attribute_groups/'.$systemGroup->getId()->toRfc4122());

        self::assertSame(422, $delete->getStatusCode());
    }

    #[Test]
    public function deleteAllowsLegacyAuditSystemGroup(): void
    {
        $client = $this->authenticatedClient();
        $tenantContext = self::getContainer()->get(TenantContext::class);
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        $tenantContext->set($tenant);
        $audit = new AttributeGroup('audit', ['en' => 'Audit'], isSystemGroup: true, autoAttached: true);
        $this->em()->persist($audit);
        $this->em()->flush();
        $tenantContext->clear();

        $delete = $client->request('DELETE', '/api/attribute_groups/'.$audit->getId()->toRfc4122());

        self::assertSame(204, $delete->getStatusCode());
    }

    #[Test]
    public function deleteBlocksWhenGroupIsAttachedToObjectType(): void
    {
        $client = $this->authenticatedClient();
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        $tenantContext = self::getContainer()->get(TenantContext::class);
        $tenantContext->set($tenant);

        $created = $client->request('POST', '/api/attribute_groups', [
            'json' => ['code' => 'shared', 'label' => ['en' => 'Shared']],
        ]);
        $id = self::extractId($created->toArray());

        // Attach the group to the built-in product ObjectType.
        $em = $this->em();
        $repo = self::getContainer()->get(AttributeGroupRepositoryInterface::class);
        $product = self::getContainer()->get(ObjectTypeRepositoryInterface::class)
            ->findBuiltInByKind(ObjectKind::Product, $tenant);
        \assert(null !== $product);
        $group = $repo->findByCode('shared', $tenant);
        \assert($group instanceof AttributeGroup);
        $em->persist(new ObjectTypeAttributeGroup($product, $group, position: 1));
        $em->flush();

        $delete = $client->request('DELETE', '/api/attribute_groups/'.$id);

        self::assertSame(409, $delete->getStatusCode());

        $tenantContext->clear();
    }

    #[Test]
    public function unauthenticatedAccessReturns401(): void
    {
        $client = static::createClient();

        $response = $client->request('GET', '/api/attribute_groups');
        self::assertSame(401, $response->getStatusCode());
    }

    #[Test]
    public function postPersistsBehaviorFlagsFromInput(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('POST', '/api/attribute_groups', [
            'json' => [
                'code' => 'wymagania-medyczne',
                'label' => ['pl' => 'Wymagania medyczne'],
                'requiredSection' => true,
                'shared' => false,
                'conditionalVisibility' => true,
            ],
        ]);

        self::assertSame(201, $response->getStatusCode());
        $payload = $response->toArray();
        self::assertTrue($payload['requiredSection']);
        self::assertFalse($payload['shared']);
        self::assertTrue($payload['conditionalVisibility']);
    }

    #[Test]
    public function patchTogglesBehaviorFlags(): void
    {
        $client = $this->authenticatedClient();
        $created = $client->request('POST', '/api/attribute_groups', [
            'json' => ['code' => 'pricing', 'label' => ['en' => 'Pricing']],
        ]);
        $id = self::extractId($created->toArray());

        $patch = $client->request('PATCH', '/api/attribute_groups/'.$id, [
            'json' => [
                'requiredSection' => true,
                'conditionalVisibility' => true,
            ],
            'headers' => ['content-type' => 'application/merge-patch+json'],
        ]);

        self::assertSame(200, $patch->getStatusCode());
        $payload = $patch->toArray();
        self::assertTrue($payload['requiredSection']);
        self::assertTrue($payload['conditionalVisibility']);
        // shared preserves prior value (default true) since it was not in the patch payload
        self::assertTrue($payload['shared']);
    }

    /**
     * @param array<int|string, mixed> $payload
     */
    private static function extractId(array $payload): string
    {
        $id = $payload['id'] ?? null;
        \assert(\is_string($id) && '' !== $id, 'AttributeGroup response did not carry an id.');

        return $id;
    }
}
