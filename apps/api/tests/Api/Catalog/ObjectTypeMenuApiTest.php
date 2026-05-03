<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

/**
 * VIEW-01c (#414) — Menu visibility + ordering on ObjectType.
 *
 * Verifies the BE half of the dynamic sidebar contract:
 * `GET /api/object_types/menu` (lean payload, sorted),
 * `PATCH /api/object_types/{id}` accepting `displayInMenu` + `menuPosition`,
 * and `POST /api/object_types/menu/reorder` rewriting positions atomically.
 */
final class ObjectTypeMenuApiTest extends CatalogApiTestCase
{
    #[Test]
    public function getMenuReturnsBuiltInsByDefault(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('GET', '/api/object_types/menu');

        self::assertResponseIsSuccessful();
        /** @var list<array{kind: string, builtIn: bool, menuPosition: int}> $payload */
        $payload = $response->toArray();

        $byKind = [];
        foreach ($payload as $row) {
            $byKind[$row['kind']] = $row;
        }
        self::assertArrayHasKey('product', $byKind);
        self::assertArrayHasKey('category', $byKind);
        self::assertArrayHasKey('asset', $byKind);
        self::assertSame(10, $byKind['product']['menuPosition']);
        self::assertSame(20, $byKind['category']['menuPosition']);
        self::assertSame(30, $byKind['asset']['menuPosition']);
        self::assertTrue($byKind['product']['builtIn']);
    }

    #[Test]
    public function patchDisplayInMenuOnCustomType(): void
    {
        $custom = $this->createCustomType('subscription');
        $id = $custom->getId()->toRfc4122();

        $client = $this->authenticatedClient();
        $response = $client->request('PATCH', '/api/object_types/'.$id, [
            'json' => ['displayInMenu' => true, 'menuPosition' => 25],
            'headers' => ['content-type' => 'application/json'],
        ]);
        self::assertResponseIsSuccessful();

        /** @var array<string, mixed> $body */
        $body = $response->toArray();
        self::assertTrue($body['displayInMenu']);
        self::assertSame(25, $body['menuPosition']);

        /** @var list<array{code: string}> $menu */
        $menu = $client->request('GET', '/api/object_types/menu')->toArray();
        $codes = array_map(static fn (array $row): string => $row['code'], $menu);
        self::assertContains('subscription', $codes);
    }

    #[Test]
    public function patchDisplayInMenuOnBuiltInIsAllowed(): void
    {
        $id = $this->objectTypeIdFor(ObjectKind::Product);
        $client = $this->authenticatedClient();

        // Different from hierarchical/hasVariants/abstract: menu fields are
        // operator UX preferences, not domain invariants — toggling on a
        // built-in must succeed (not 403).
        $response = $client->request('PATCH', '/api/object_types/'.$id, [
            'json' => ['displayInMenu' => false],
            'headers' => ['content-type' => 'application/json'],
        ]);
        self::assertResponseIsSuccessful();

        /** @var list<array{kind: string}> $menu */
        $menu = $client->request('GET', '/api/object_types/menu')->toArray();
        $kinds = array_map(static fn (array $row): string => $row['kind'], $menu);
        self::assertNotContains('product', $kinds);
    }

    #[Test]
    public function reorderMenuPersistsNewOrder(): void
    {
        $client = $this->authenticatedClient();

        $productId = $this->objectTypeIdFor(ObjectKind::Product);
        $categoryId = $this->objectTypeIdFor(ObjectKind::Category);
        $assetId = $this->objectTypeIdFor(ObjectKind::Asset);
        $brandId = $this->objectTypeIdFor(ObjectKind::Brand);

        // Reverse the default 10/20/30/40 order.
        $client->request('POST', '/api/object_types/menu/reorder', [
            'json' => ['order' => [$brandId, $assetId, $categoryId, $productId]],
            'headers' => ['content-type' => 'application/json'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        /** @var list<array{kind: string}> $menu */
        $menu = $client->request('GET', '/api/object_types/menu')->toArray();
        $kindOrder = array_map(static fn (array $row): string => $row['kind'], $menu);
        self::assertSame(['brand', 'asset', 'category', 'product'], $kindOrder);
    }

    #[Test]
    public function reorderMenuRejectsHiddenObjectType(): void
    {
        $custom = $this->createCustomType('hidden_one');
        $client = $this->authenticatedClient();

        $client->request('POST', '/api/object_types/menu/reorder', [
            'json' => ['order' => [$custom->getId()->toRfc4122()]],
            'headers' => ['content-type' => 'application/json'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[Test]
    public function reorderMenuRejectsUnknownUuid(): void
    {
        $client = $this->authenticatedClient();
        $client->request('POST', '/api/object_types/menu/reorder', [
            'json' => ['order' => ['00000000-0000-7000-8000-000000000000']],
            'headers' => ['content-type' => 'application/json'],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[Test]
    public function serializerExposesNewFieldsInGetCollection(): void
    {
        $client = $this->authenticatedClient();
        /** @var array<string, mixed> $payload */
        $payload = $client->request('GET', '/api/object_types')->toArray();
        $items = $payload['hydra:member'] ?? $payload['member'] ?? [];
        self::assertIsArray($items);
        self::assertNotEmpty($items);

        foreach ($items as $row) {
            self::assertIsArray($row);
            self::assertArrayHasKey('displayInMenu', $row);
            self::assertArrayHasKey('menuPosition', $row);
        }
    }

    private function createCustomType(string $code): ObjectType
    {
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        self::getContainer()->get(TenantContext::class)->set($tenant);

        try {
            $type = new ObjectType($code, ObjectKind::Custom, ['en' => ucfirst($code)]);
            $this->em()->persist($type);
            $this->em()->flush();

            // Sanity: refetch through the repo so identity-mapped tenant is set.
            $live = self::getContainer()
                ->get(ObjectTypeRepositoryInterface::class)
                ->findById($type->getId());
            \assert(null !== $live);

            return $live;
        } finally {
            self::getContainer()->get(TenantContext::class)->clear();
        }
    }
}
