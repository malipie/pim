<?php

declare(strict_types=1);

namespace App\Tests\Api\Export;

use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Shared\Domain\Tenant;
use App\Tests\Api\Catalog\CatalogApiTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * EXR-04 (#1380) — entity_type / object_type_id contract on the export API.
 *
 * Covers the validation matrix (custom_module ↔ object_type_id rules,
 * structural-type scope rules), backward compatibility for payloads without
 * entity_type, tenant isolation of object_type_id, and the EXR-04 execution
 * gate (only `product` is runnable until EXR-05/06).
 */
final class ExportEntityTypeApiTest extends CatalogApiTestCase
{
    // ── Sync export endpoint (POST /api/products/export) ──────────────────

    #[Test]
    public function syncExportWithoutEntityTypeDefaultsToProduct(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('POST', '/api/products/export', [
            'json' => [
                'format' => 'csv',
                'target_scope' => 'all',
                'selected_columns' => ['sku'],
            ],
        ]);

        // Empty catalog → sync path streams the (header-only) file.
        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function syncExportRejectsCustomModuleWithoutObjectTypeId(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('POST', '/api/products/export', [
            'json' => [
                'entity_type' => 'custom_module',
                'format' => 'csv',
                'target_scope' => 'all',
                'selected_columns' => ['sku'],
            ],
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    #[Test]
    public function syncExportRejectsCustomModulePointingAtBuiltInObjectType(): void
    {
        $builtInProduct = $this->objectTypeIdFor(ObjectKind::Product);

        $client = $this->authenticatedClient();
        $response = $client->request('POST', '/api/products/export', [
            'json' => [
                'entity_type' => 'custom_module',
                'object_type_id' => $builtInProduct,
                'format' => 'csv',
                'target_scope' => 'all',
                'selected_columns' => ['sku'],
            ],
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    #[Test]
    public function syncExportRejectsObjectTypeIdForProduct(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('POST', '/api/products/export', [
            'json' => [
                'entity_type' => 'product',
                'object_type_id' => $this->objectTypeIdFor(ObjectKind::Product),
                'format' => 'csv',
                'target_scope' => 'all',
                'selected_columns' => ['sku'],
            ],
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    #[Test]
    public function syncExportRejectsStructuralTypeWithNonAllScope(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('POST', '/api/products/export', [
            'json' => [
                'entity_type' => 'categories',
                'format' => 'csv',
                'target_scope' => 'filter',
                'selected_columns' => ['code'],
            ],
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    #[Test]
    public function syncExportRunsValidCustomModule(): void
    {
        // EXR-05: custom_module now runs through the catalog-object pipeline.
        // Empty custom catalog → sync path streams the header-only file.
        $customId = $this->createCustomObjectType();

        $client = $this->authenticatedClient();
        $response = $client->request('POST', '/api/products/export', [
            'json' => [
                'entity_type' => 'custom_module',
                'object_type_id' => $customId,
                'format' => 'csv',
                'target_scope' => 'all',
                'selected_columns' => ['sku'],
            ],
        ]);

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function syncExportHidesObjectTypeFromAnotherTenant(): void
    {
        $foreignId = $this->createCustomObjectTypeInOtherTenant();

        $client = $this->authenticatedClient();
        $response = $client->request('POST', '/api/products/export', [
            'json' => [
                'entity_type' => 'custom_module',
                'object_type_id' => $foreignId,
                'format' => 'csv',
                'target_scope' => 'all',
                'selected_columns' => ['sku'],
            ],
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    // ── Profile CRUD (POST /api/exports/profiles) ─────────────────────────

    #[Test]
    public function profileWithoutEntityTypeDefaultsToProduct(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('POST', '/api/exports/profiles', [
            'json' => [
                'name' => 'Legacy profile',
                'config' => ['selected_columns' => ['sku']],
            ],
        ]);

        self::assertSame(201, $response->getStatusCode());
        $body = $response->toArray(false);
        self::assertSame('product', $body['entity_type']);
        self::assertNull($body['object_type_id']);
    }

    #[Test]
    public function profileStoresCustomModuleWithObjectTypeId(): void
    {
        $customId = $this->createCustomObjectType();

        $client = $this->authenticatedClient();
        $response = $client->request('POST', '/api/exports/profiles', [
            'json' => [
                'name' => 'Services export',
                'entity_type' => 'custom_module',
                'object_type_id' => $customId,
                'config' => ['selected_columns' => ['sku', 'name']],
            ],
        ]);

        self::assertSame(201, $response->getStatusCode());
        $body = $response->toArray(false);
        self::assertSame('custom_module', $body['entity_type']);
        self::assertSame($customId, $body['object_type_id']);
    }

    #[Test]
    public function profileStoresStructuralEntityType(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('POST', '/api/exports/profiles', [
            'json' => [
                'name' => 'Schema export',
                'entity_type' => 'module_schema',
                'config' => ['selected_columns' => ['object_type_code']],
            ],
        ]);

        self::assertSame(201, $response->getStatusCode());
        $body = $response->toArray(false);
        self::assertSame('module_schema', $body['entity_type']);
        self::assertNull($body['object_type_id']);
    }

    #[Test]
    public function profileRejectsCustomModuleWithoutObjectTypeId(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('POST', '/api/exports/profiles', [
            'json' => [
                'name' => 'Broken custom',
                'entity_type' => 'custom_module',
                'config' => ['selected_columns' => ['sku']],
            ],
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    #[Test]
    public function profileRejectsObjectTypeIdForStructuralType(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('POST', '/api/exports/profiles', [
            'json' => [
                'name' => 'Bad categories',
                'entity_type' => 'categories',
                'object_type_id' => $this->objectTypeIdFor(ObjectKind::Product),
                'config' => ['selected_columns' => ['code']],
            ],
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    #[Test]
    public function profileRejectsStructuralTypePinnedToNarrowScope(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('POST', '/api/exports/profiles', [
            'json' => [
                'name' => 'Scoped schema',
                'entity_type' => 'attributes_groups',
                'config' => [
                    'selected_columns' => ['code'],
                    'default_target_scope' => 'selected',
                ],
            ],
        ]);

        self::assertSame(422, $response->getStatusCode());
    }

    // ── Fixtures ──────────────────────────────────────────────────────────

    private function createCustomObjectType(string $code = 'services'): string
    {
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);

        $objectType = new ObjectType($code, ObjectKind::Custom, ['pl' => 'Usługi', 'en' => 'Services']);
        $objectType->assignTenant($tenant);
        $this->em()->persist($objectType);
        $this->em()->flush();

        return $objectType->getId()->toRfc4122();
    }

    private function createCustomObjectTypeInOtherTenant(): string
    {
        $other = new Tenant('other', 'Other Tenant');
        $this->em()->persist($other);

        $objectType = new ObjectType('foreign-module', ObjectKind::Custom, ['pl' => 'Obcy', 'en' => 'Foreign']);
        $objectType->assignTenant($other);
        $this->em()->persist($objectType);
        $this->em()->flush();

        return $objectType->getId()->toRfc4122();
    }
}
