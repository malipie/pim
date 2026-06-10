<?php

declare(strict_types=1);

namespace App\Tests\Api\Export;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Shared\Domain\Tenant;
use App\Tests\Api\Catalog\CatalogApiTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * EXR-07 (#1383) — preflight count + sync/async routing contract.
 */
final class ExportPreflightApiTest extends CatalogApiTestCase
{
    #[Test]
    public function countsAllObjectsOfACustomModuleInSyncMode(): void
    {
        $tenant = $this->tenant();
        $services = $this->customObjectType($tenant, 'services');
        $this->object($tenant, $services, 'SRV-1');
        $this->object($tenant, $services, 'SRV-2');
        $this->object($tenant, $services, 'SRV-3');
        $this->em()->flush();

        $body = $this->preflight([
            'entity_type' => 'custom_module',
            'object_type_id' => $services->getId()->toRfc4122(),
            'target_scope' => 'all',
        ]);

        self::assertSame(3, $body['count']);
        self::assertSame('sync', $body['mode']);
        self::assertSame(100, $body['threshold']);
        self::assertSame(100000, $body['soft_cap']);
        self::assertFalse($body['exceeds_cap']);
    }

    #[Test]
    public function modeFlipsToAsyncAtThreshold(): void
    {
        $tenant = $this->tenant();
        $bulk = $this->customObjectType($tenant, 'bulk');
        for ($i = 1; $i <= 100; ++$i) {
            $this->object($tenant, $bulk, sprintf('BULK-%03d', $i));
        }
        $this->em()->flush();

        $body = $this->preflight([
            'entity_type' => 'custom_module',
            'object_type_id' => $bulk->getId()->toRfc4122(),
            'target_scope' => 'all',
        ]);

        self::assertSame(100, $body['count']);
        self::assertSame('async', $body['mode']);
        self::assertFalse($body['exceeds_cap']);
    }

    #[Test]
    public function filterCountIsScopedToTheObjectType(): void
    {
        $tenant = $this->tenant();
        $services = $this->customObjectType($tenant, 'filtered-services');
        $this->object($tenant, $services, 'F-1', ['brand' => 'Festo']);
        $this->object($tenant, $services, 'F-2', ['brand' => 'Festo']);
        $this->object($tenant, $services, 'B-1', ['brand' => 'Bosch']);

        // A Festo object under a DIFFERENT ObjectType must not be counted.
        $other = $this->customObjectType($tenant, 'other-services');
        $this->object($tenant, $other, 'X-1', ['brand' => 'Festo']);
        $this->em()->flush();

        $body = $this->preflight([
            'entity_type' => 'custom_module',
            'object_type_id' => $services->getId()->toRfc4122(),
            'target_scope' => 'filter',
            'filter' => [
                'operator' => 'AND',
                'conditions' => [
                    ['attr' => 'brand', 'op' => '=', 'value' => 'Festo'],
                ],
            ],
        ]);

        self::assertSame(2, $body['count']);
        self::assertSame('sync', $body['mode']);
    }

    #[Test]
    public function countsUniqueSelectedIds(): void
    {
        $id = '019eae00-0000-7000-8000-000000000001';
        $body = $this->preflight([
            'entity_type' => 'product',
            'target_scope' => 'selected',
            'selected_ids' => [$id, $id, '019eae00-0000-7000-8000-000000000002'],
        ]);

        self::assertSame(2, $body['count']);
        self::assertSame('sync', $body['mode']);
    }

    #[Test]
    public function productAllCountIsZeroOnEmptyCatalog(): void
    {
        $body = $this->preflight([
            'entity_type' => 'product',
            'target_scope' => 'all',
        ]);

        self::assertSame(0, $body['count']);
        self::assertSame('sync', $body['mode']);
    }

    #[Test]
    public function rejectsStructuralEntityType(): void
    {
        $response = $this->preflightRaw([
            'entity_type' => 'module_schema',
            'target_scope' => 'all',
        ]);

        self::assertSame(422, $response);
    }

    #[Test]
    public function rejectsCustomModuleWithoutObjectTypeId(): void
    {
        $response = $this->preflightRaw([
            'entity_type' => 'custom_module',
            'target_scope' => 'all',
        ]);

        self::assertSame(422, $response);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function preflight(array $payload): array
    {
        $client = $this->authenticatedClient();
        $response = $client->request('POST', '/api/exports/preflight', ['json' => $payload]);
        self::assertSame(200, $response->getStatusCode());

        /** @var array<string, mixed> $data */
        $data = $response->toArray(false);

        return $data;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function preflightRaw(array $payload): int
    {
        $client = $this->authenticatedClient();

        return $client->request('POST', '/api/exports/preflight', ['json' => $payload])->getStatusCode();
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

    /**
     * @param array<string, mixed>|null $indexed
     */
    private function object(Tenant $tenant, ObjectType $objectType, string $code, ?array $indexed = null): void
    {
        $object = new CatalogObject($objectType, $code);
        $object->assignTenant($tenant);
        if (null !== $indexed) {
            $object->updateAttributeIndex($indexed);
        }
        $this->em()->persist($object);
    }
}
