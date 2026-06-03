<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use App\Catalog\Domain\Entity\AttributeGroup;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\AttributeGroupRepositoryInterface;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;

use const JSON_THROW_ON_ERROR;

/**
 * VIEW-04 (#408) — coverage for the four CategoryAttributeGroup
 * routes (declare/list/detach/reorder).
 */
final class CategoryAttributeGroupApiTest extends CatalogApiTestCase
{
    #[Test]
    public function declarePersistsJunction(): void
    {
        $client = $this->authenticatedClient();
        $categoryId = $this->createCategory($client, 'declare_target');
        $groupId = $this->createBusinessGroup('biz_a');

        $response = $client->request('POST', "/api/categories/{$categoryId}/attribute_groups", [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'groupId' => $groupId,
                'targetObjectTypeKind' => 'product',
            ], JSON_THROW_ON_ERROR),
        ]);

        self::assertResponseStatusCodeSame(201);
        $body = $response->toArray();
        self::assertSame($categoryId, $body['categoryId'] ?? null);
        $group = $body['group'] ?? null;
        \assert(\is_array($group));
        self::assertSame($groupId, $group['id'] ?? null);
        self::assertSame(0, $body['position'] ?? null);
        $targetObjectType = $body['targetObjectType'] ?? null;
        \assert(\is_array($targetObjectType));
        self::assertSame('product', $targetObjectType['kind'] ?? null);
    }

    #[Test]
    public function declareIsIdempotentOnDuplicate(): void
    {
        $client = $this->authenticatedClient();
        $categoryId = $this->createCategory($client, 'idem_target');
        $groupId = $this->createBusinessGroup('biz_idem');

        $client->request('POST', "/api/categories/{$categoryId}/attribute_groups", [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'groupId' => $groupId,
                'targetObjectTypeKind' => 'product',
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(201);

        $client->request('POST', "/api/categories/{$categoryId}/attribute_groups", [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'groupId' => $groupId,
                'targetObjectTypeKind' => 'product',
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(200); // existing junction returned as 200, not duplicated
    }

    #[Test]
    public function listReturnsDeclaredGroupsForTarget(): void
    {
        $client = $this->authenticatedClient();
        $categoryId = $this->createCategory($client, 'list_target');
        $groupId = $this->createBusinessGroup('biz_list');

        $client->request('POST', "/api/categories/{$categoryId}/attribute_groups", [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'groupId' => $groupId,
                'targetObjectTypeKind' => 'product',
            ], JSON_THROW_ON_ERROR),
        ]);

        $list = $client->request('GET', "/api/categories/{$categoryId}/attribute_groups?targetObjectTypeKind=product");
        self::assertResponseStatusCodeSame(200);

        $body = $list->toArray();
        $declared = $body['declaredGroups'] ?? null;
        \assert(\is_array($declared));
        self::assertCount(1, $declared);
        $first = $declared[0] ?? null;
        \assert(\is_array($first));
        self::assertSame($groupId, $first['groupId'] ?? null);
    }

    #[Test]
    public function detachRemovesJunction(): void
    {
        $client = $this->authenticatedClient();
        $categoryId = $this->createCategory($client, 'detach_target');
        $groupId = $this->createBusinessGroup('biz_detach');
        $targetTypeId = $this->objectTypeIdFor(ObjectKind::Product);

        $client->request('POST', "/api/categories/{$categoryId}/attribute_groups", [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'groupId' => $groupId,
                'targetObjectTypeKind' => 'product',
            ], JSON_THROW_ON_ERROR),
        ]);

        $client->request('DELETE', "/api/categories/{$categoryId}/attribute_groups/{$groupId}/{$targetTypeId}");
        self::assertResponseStatusCodeSame(204);

        $list = $client->request('GET', "/api/categories/{$categoryId}/attribute_groups?targetObjectTypeKind=product");
        $declared = $list->toArray()['declaredGroups'] ?? null;
        \assert(\is_array($declared));
        self::assertCount(0, $declared);
    }

    #[Test]
    public function reorderUpdatesPosition(): void
    {
        $client = $this->authenticatedClient();
        $categoryId = $this->createCategory($client, 'reorder_target');
        $groupId = $this->createBusinessGroup('biz_reorder');
        $targetTypeId = $this->objectTypeIdFor(ObjectKind::Product);

        $client->request('POST', "/api/categories/{$categoryId}/attribute_groups", [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'groupId' => $groupId,
                'targetObjectTypeKind' => 'product',
            ], JSON_THROW_ON_ERROR),
        ]);

        $response = $client->request('PATCH', "/api/categories/{$categoryId}/attribute_groups/{$groupId}/{$targetTypeId}", [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode(['position' => 3], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(200);
        self::assertSame(3, $response->toArray()['position'] ?? null);
    }

    #[Test]
    public function declareUnauthorizedReturns401(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/categories/00000000-0000-0000-0000-000000000000/attribute_groups', [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'groupId' => '00000000-0000-0000-0000-000000000001',
                'targetObjectTypeKind' => 'product',
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function declareOnNonexistentCategoryReturns404(): void
    {
        $client = $this->authenticatedClient();
        $client->request('POST', '/api/categories/00000000-0000-0000-0000-000000000099/attribute_groups', [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'groupId' => '00000000-0000-0000-0000-000000000001',
                'targetObjectTypeKind' => 'product',
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function declareWithUnknownKindReturns400(): void
    {
        $client = $this->authenticatedClient();
        $categoryId = $this->createCategory($client, 'unknown_kind');
        $groupId = $this->createBusinessGroup('biz_unknown');

        $client->request('POST', "/api/categories/{$categoryId}/attribute_groups", [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'groupId' => $groupId,
                'targetObjectTypeKind' => 'made_up_kind',
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(400);
    }

    private function createCategory(\ApiPlatform\Symfony\Bundle\Test\Client $client, string $code): string
    {
        $response = $client->request('POST', '/api/categories', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => $code,
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Category),
                'categoryTargetObjectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ]);
        $id = $response->toArray()['id'] ?? null;
        \assert(\is_string($id));

        return $id;
    }

    private function createBusinessGroup(string $code): string
    {
        // Tenant is stamped by TenantAssignmentListener on prePersist via the
        // current TenantContext — set it explicitly so the test can run
        // outside an HTTP request that would normally bind it.
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        self::getContainer()->get(\App\Shared\Application\TenantContext::class)->set($tenant);

        $group = new AttributeGroup($code, ['pl' => $code, 'en' => $code]);
        self::getContainer()->get(AttributeGroupRepositoryInterface::class)->save($group);

        return $group->getId()->toRfc4122();
    }

    #[Test]
    public function declareByObjectTypeIdWorksForCustomTree(): void
    {
        // ADR-015 PR-E — declaring a group on a custom-OT category tree via
        // targetObjectTypeId works (previously kind='custom' → 404
        // "Built-in ObjectType for kind 'custom' not found").
        $client = $this->authenticatedClient();
        $customOt = $this->seedCategorizableObjectType('cars_declare', 'Cars declare');
        $categoryId = $client->request('POST', '/api/categories', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'cars_root',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Category),
                'categoryTargetObjectTypeId' => $customOt,
            ], JSON_THROW_ON_ERROR),
        ])->toArray()['id'] ?? null;
        \assert(\is_string($categoryId));
        $groupId = $this->createBusinessGroup('cars_group');

        $response = $client->request('POST', "/api/categories/{$categoryId}/attribute_groups", [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'groupId' => $groupId,
                'targetObjectTypeId' => $customOt,
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(201);
        $targetObjectType = $response->toArray()['targetObjectType'] ?? null;
        \assert(\is_array($targetObjectType));
        self::assertSame($customOt, $targetObjectType['id'] ?? null);
    }

    private function seedCategorizableObjectType(string $code, string $label): string
    {
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        $ctx = self::getContainer()->get(\App\Shared\Application\TenantContext::class);
        $ctx->set($tenant);

        $ot = new \App\Catalog\Domain\Entity\ObjectType($code, ObjectKind::Custom, ['pl' => $label, 'en' => $label]);
        $ot->setCategorizable(true);
        $em = $this->em();
        $em->persist($ot);
        $em->flush();
        $ctx->clear();

        return $ot->getId()->toRfc4122();
    }
}
