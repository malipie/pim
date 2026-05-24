<?php

declare(strict_types=1);

namespace App\Tests\Api\Identity;

use App\Catalog\Domain\ObjectKind;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use App\Tests\Api\Catalog\CatalogApiTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * VIEW-08 (#427) — `/api/menu_configuration` (GET/PUT/effective) contract.
 */
final class MenuConfigurationApiTest extends CatalogApiTestCase
{
    #[Test]
    public function getReturnsLazySeededDefaultMenu(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('GET', '/api/menu_configuration');

        self::assertSame(200, $response->getStatusCode());
        $payload = $response->toArray();

        self::assertArrayHasKey('items', $payload);
        /** @var list<array{kind: string, ref: string, position: int, visible: bool}> $items */
        $items = $payload['items'];
        // 1 (dashboard) + 1 (object_type:product) + 6 (rest of system items
        // — system list ma 7 keys, minus dashboard interleaved separately).
        self::assertCount(8, $items);

        // Order matches DefaultMenuSeeder — Pulpit, Produkty, Katalogi PDF,
        // Multimedia, Workflow, Integracje, Ustawienia, Modelowanie.
        self::assertSame('system', $items[0]['kind']);
        self::assertSame('dashboard', $items[0]['ref']);
        self::assertSame('object_type', $items[1]['kind']);
        self::assertSame('system', $items[2]['kind']);
        self::assertSame('catalogs_pdf', $items[2]['ref']);
        self::assertSame('integrations', $items[5]['ref']);
        self::assertSame('modeling', $items[7]['ref']);

        // Services intentionally absent.
        $refs = array_column($items, 'ref');
        self::assertNotContains('services', $refs);
    }

    #[Test]
    public function effectiveExposesProductInVisibleAndOthersInAvailable(): void
    {
        // ADR-014 / MOD-10 (#902): Brand is no longer a built-in ObjectType.
        // Provision a custom OT (`vendor`) instead and flip its
        // `exposeToMainMenu` to exercise the same code path.
        $vendorId = $this->provisionCustomExposedObjectType('vendor');

        $client = $this->authenticatedClient();
        $response = $client->request('GET', '/api/menu_configuration/effective');

        self::assertSame(200, $response->getStatusCode());
        $payload = $response->toArray();

        /** @var list<array{id: string}> $visible */
        $visible = $payload['visible'];
        $visibleRefs = array_column($visible, 'id');
        self::assertContains('object_type:'.$this->productId(), $visibleRefs);

        /** @var list<array{id: string}> $available */
        $available = $payload['available'];
        $availableRefs = array_column($available, 'id');
        self::assertContains('object_type:'.$vendorId, $availableRefs);
    }

    #[Test]
    public function putReplacesItemsAtomically(): void
    {
        $client = $this->authenticatedClient();

        // Reverse order vs. the default seed (settings ↔ modeling stay protected).
        $items = [
            ['kind' => 'system', 'ref' => 'modeling',     'position' => 0, 'visible' => true],
            ['kind' => 'system', 'ref' => 'settings',     'position' => 1, 'visible' => true],
            ['kind' => 'system', 'ref' => 'integrations', 'position' => 2, 'visible' => true],
            ['kind' => 'system', 'ref' => 'workflow',     'position' => 3, 'visible' => true],
            ['kind' => 'system', 'ref' => 'multimedia',   'position' => 4, 'visible' => true],
            ['kind' => 'system', 'ref' => 'catalogs_pdf', 'position' => 5, 'visible' => true],
            ['kind' => 'object_type', 'ref' => $this->productId(), 'position' => 6, 'visible' => true],
            ['kind' => 'system', 'ref' => 'dashboard',    'position' => 7, 'visible' => true],
        ];

        $response = $client->request('PUT', '/api/menu_configuration', [
            'json' => ['items' => $items],
        ]);

        self::assertSame(200, $response->getStatusCode());
        $payload = $response->toArray();
        /** @var list<array{kind: string, ref: string}> $newItems */
        $newItems = $payload['items'];
        self::assertSame('modeling', $newItems[0]['ref']);
        self::assertSame('dashboard', $newItems[7]['ref']);
    }

    #[Test]
    public function putRejectsHidingProtectedSystemItem(): void
    {
        $client = $this->authenticatedClient();

        $response = $client->request('PUT', '/api/menu_configuration', [
            'json' => [
                'items' => [
                    ['kind' => 'system', 'ref' => 'settings', 'position' => 0, 'visible' => false],
                ],
            ],
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    #[Test]
    public function putRejectsAssetObjectTypeRef(): void
    {
        $client = $this->authenticatedClient();

        $response = $client->request('PUT', '/api/menu_configuration', [
            'json' => [
                'items' => [
                    ['kind' => 'object_type', 'ref' => $this->assetId(), 'position' => 0, 'visible' => true],
                ],
            ],
        ]);

        // Asset can never be exposed (kind=asset blocked at service level)
        // — operator never even gets exposeToMainMenu=true on it. Here we
        // bypass that flag and the service still refuses.
        self::assertSame(422, $response->getStatusCode());
    }

    #[Test]
    public function putRejectsObjectTypeWithoutExposeFlag(): void
    {
        $client = $this->authenticatedClient();

        $response = $client->request('PUT', '/api/menu_configuration', [
            'json' => [
                'items' => [
                    ['kind' => 'object_type', 'ref' => $this->categoryId(), 'position' => 0, 'visible' => true],
                ],
            ],
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    #[Test]
    public function putRejectsDuplicatePositions(): void
    {
        $client = $this->authenticatedClient();

        $response = $client->request('PUT', '/api/menu_configuration', [
            'json' => [
                'items' => [
                    ['kind' => 'system', 'ref' => 'dashboard', 'position' => 0, 'visible' => true],
                    ['kind' => 'system', 'ref' => 'modeling', 'position' => 0, 'visible' => true],
                ],
            ],
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    #[Test]
    public function patchObjectTypeWithExposeToMainMenuFlipsAvailability(): void
    {
        $client = $this->authenticatedClient();

        // ADR-014 / MOD-10 (#902): Brand demoted from built-in. Provision a
        // custom OT (`supplier`) with the toggle initially off and exercise
        // the PATCH → /effective flow that used to ride on Brand.
        $supplierId = $this->provisionCustomObjectType('supplier');
        $response = $client->request('PATCH', '/api/object_types/'.$supplierId, [
            'json' => ['exposeToMainMenu' => true],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);
        self::assertSame(200, $response->getStatusCode());
        $body = $response->toArray();
        self::assertTrue($body['exposeToMainMenu']);

        // After the toggle, /effective lists the custom OT as available.
        $effective = $client->request('GET', '/api/menu_configuration/effective')->toArray();
        /** @var list<array{id: string}> $availableItems */
        $availableItems = $effective['available'];
        $availableRefs = array_column($availableItems, 'id');
        self::assertContains('object_type:'.$supplierId, $availableRefs);
    }

    #[Test]
    public function patchAssetWithExposeToMainMenuIsRejected(): void
    {
        $client = $this->authenticatedClient();

        $response = $client->request('PATCH', '/api/object_types/'.$this->assetId(), [
            'json' => ['exposeToMainMenu' => true],
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    private function productId(): string
    {
        return $this->objectTypeIdFor(ObjectKind::Product);
    }

    private function categoryId(): string
    {
        return $this->objectTypeIdFor(ObjectKind::Category);
    }

    private function assetId(): string
    {
        return $this->objectTypeIdFor(ObjectKind::Asset);
    }

    /**
     * ADR-014 / MOD-10 (#902): replacement for the legacy `brandId()` —
     * provisions a custom ObjectType with the operator-driven
     * `expose_to_main_menu=false` default. Returns the UUID for use in
     * /api/object_types/{id} flows. Tenant context is set so the
     * `TenantAssignmentListener` can stamp it.
     */
    private function provisionCustomObjectType(string $code): string
    {
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        $tenantContext = self::getContainer()->get(TenantContext::class);
        $tenantContext->set($tenant);

        $type = new \App\Catalog\Domain\Entity\ObjectType(
            $code,
            ObjectKind::Custom,
            ['pl' => ucfirst($code), 'en' => ucfirst($code)],
        );
        $this->em()->persist($type);
        $this->em()->flush();

        $tenantContext->clear();

        return $type->getId()->toRfc4122();
    }

    /**
     * Like {@see provisionCustomObjectType} but flips `exposeToMainMenu`
     * to TRUE in the same transaction — used by the "browse /effective"
     * test that needs the OT to already be available.
     */
    private function provisionCustomExposedObjectType(string $code): string
    {
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        $tenantContext = self::getContainer()->get(TenantContext::class);
        $tenantContext->set($tenant);

        $type = new \App\Catalog\Domain\Entity\ObjectType(
            $code,
            ObjectKind::Custom,
            ['pl' => ucfirst($code), 'en' => ucfirst($code)],
        );
        $type->setExposeToMainMenu(true);
        $this->em()->persist($type);
        $this->em()->flush();

        $tenantContext->clear();

        return $type->getId()->toRfc4122();
    }
}
