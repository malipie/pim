<?php

declare(strict_types=1);

namespace App\Tests\Api\Export;

use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\Entity\ObjectValue;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Provenance;
use App\Shared\Domain\Tenant;
use App\Tests\Api\Catalog\CatalogApiTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * EXR-05 (#1381) — the export pipeline generalised to any ObjectType.
 *
 * Exercises the custom_module path end-to-end through the real sync controller:
 * rows + attribute values come out, the target set is scoped to the requested
 * ObjectType, and a freshly-invented attribute exports with zero code change
 * (schema dynamism — the EAV requirement).
 */
final class CustomModuleExportApiTest extends CatalogApiTestCase
{
    #[Test]
    public function exportsCustomModuleRowsScopedToItsObjectType(): void
    {
        $tenant = $this->tenant();

        $services = $this->customObjectType($tenant, 'services');
        $horsepower = $this->attribute($tenant, 'horsepower');
        $this->object($tenant, $services, 'SRV-1', $horsepower, '150');
        $this->object($tenant, $services, 'SRV-2', $horsepower, '200');

        // A different custom ObjectType in the SAME tenant must not bleed in.
        $salons = $this->customObjectType($tenant, 'salons');
        $this->object($tenant, $salons, 'SAL-1', $horsepower, '999');

        $this->em()->flush();

        $csv = $this->runSyncExport($services->getId()->toRfc4122(), ['sku', 'horsepower']);

        self::assertStringContainsString('SRV-1', $csv);
        self::assertStringContainsString('150', $csv);
        self::assertStringContainsString('SRV-2', $csv);
        self::assertStringContainsString('200', $csv);
        self::assertStringNotContainsString('SAL-1', $csv);
        self::assertStringNotContainsString('999', $csv);
    }

    #[Test]
    public function exportsAFreshlyAddedAttributeWithoutCodeChange(): void
    {
        $tenant = $this->tenant();

        $collections = $this->customObjectType($tenant, 'collections');
        // `torque` exists in no seed, fixture, or hardcode — it is invented here.
        $torque = $this->attribute($tenant, 'torque');
        $this->object($tenant, $collections, 'COL-1', $torque, '250');
        $this->em()->flush();

        $csv = $this->runSyncExport($collections->getId()->toRfc4122(), ['sku', 'torque']);

        self::assertStringContainsString('COL-1', $csv);
        self::assertStringContainsString('250', $csv);
    }

    /**
     * @param list<string> $columns
     */
    private function runSyncExport(string $objectTypeId, array $columns): string
    {
        $client = $this->authenticatedClient();
        $response = $client->request('POST', '/api/products/export', [
            'json' => [
                'entity_type' => 'custom_module',
                'object_type_id' => $objectTypeId,
                'format' => 'csv',
                'target_scope' => 'all',
                'selected_columns' => $columns,
            ],
        ]);

        self::assertSame(200, $response->getStatusCode());

        return $response->getContent(false);
    }

    private function tenant(): Tenant
    {
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);

        return $tenant;
    }

    private function customObjectType(Tenant $tenant, string $code): ObjectType
    {
        $objectType = new ObjectType($code, ObjectKind::Custom, ['pl' => ucfirst($code), 'en' => ucfirst($code)]);
        $objectType->assignTenant($tenant);
        $this->em()->persist($objectType);

        return $objectType;
    }

    private function attribute(Tenant $tenant, string $code): Attribute
    {
        $attribute = new Attribute($code, ['pl' => ucfirst($code), 'en' => ucfirst($code)], AttributeType::Text);
        $attribute->assignTenant($tenant);
        $this->em()->persist($attribute);

        return $attribute;
    }

    private function object(Tenant $tenant, ObjectType $objectType, string $code, Attribute $attribute, string $value): void
    {
        $object = new CatalogObject($objectType, $code);
        $object->assignTenant($tenant);
        $this->em()->persist($object);

        $objectValue = new ObjectValue($object, $attribute, ['value' => $value], Provenance::Manual);
        $objectValue->assignTenant($tenant);
        $this->em()->persist($objectValue);
    }
}
