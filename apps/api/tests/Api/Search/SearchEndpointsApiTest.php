<?php

declare(strict_types=1);

namespace App\Tests\Api\Search;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Search\Application\BulkCatalogObjectIndexer;
use App\Search\Application\IndexSettingsTemplate;
use App\Search\Application\MeilisearchIndexProvisioner;
use App\Search\Infrastructure\MeilisearchClientFactory;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use App\Tests\Api\Catalog\CatalogApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use Throwable;

use const JSON_THROW_ON_ERROR;

/**
 * Coverage for #52 (0.5.4) — `/api/{kind}/search` endpoints.
 *
 * Hits the real Meilisearch container (already running in CI Playwright
 * stack + dev). Each test seeds a couple of CatalogObject rows + drives
 * the bulk indexer to push them, then asserts query / faceting / tenant
 * scoping over the live index.
 */
final class SearchEndpointsApiTest extends CatalogApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Skip when Meilisearch is not in the test stack (PHPUnit CI job
        // only provisions Postgres + Redis). The Playwright job runs the
        // full docker-compose stack including Meili — these contracts
        // are exercised there end-to-end.
        try {
            self::getContainer()->get(MeilisearchClientFactory::class)->create()->health();
        } catch (Throwable) {
            self::markTestSkipped('Meilisearch container not available; covered by Playwright stack.');
        }

        // Provision indexes + drop residue from earlier tests so each
        // test sees only its own seeded rows.
        $provisioner = self::getContainer()->get(MeilisearchIndexProvisioner::class);
        $provisioner->provision();

        $client = self::getContainer()->get(MeilisearchClientFactory::class)->create();
        foreach (IndexSettingsTemplate::indexedKinds() as $kind) {
            $client->index(IndexSettingsTemplate::indexName($kind))->deleteAllDocuments();
        }
        // Wait for the delete tasks to settle before pushing fresh data.
        usleep(300_000);
    }

    #[Test]
    public function searchProductsScopedToCurrentTenant(): void
    {
        $this->seedProduct('NIKE-RED', ['brand' => 'Nike', 'color' => 'red']);
        $this->seedProduct('ADIDAS-RED', ['brand' => 'Adidas', 'color' => 'red']);
        $this->forceReindex(ObjectKind::Product);

        $client = $this->authenticatedClient();
        $body = $client->request('GET', '/api/search/products?q=red')->toArray();

        self::assertArrayHasKey('hits', $body);
        self::assertGreaterThanOrEqual(2, $body['totalHits'] ?? 0);
    }

    #[Test]
    public function searchHonoursStatusFilter(): void
    {
        $this->seedProduct('PUB-1', enabled: true);
        $this->seedProduct('PUB-2', enabled: true);
        $this->seedProduct('DISABLED', enabled: false);
        $this->forceReindex(ObjectKind::Product);

        $client = $this->authenticatedClient();
        $body = $client->request('GET', '/api/search/products?filter[enabled]=true')->toArray();

        self::assertGreaterThanOrEqual(2, $body['totalHits'] ?? 0);
        $hits = $body['hits'] ?? [];
        \assert(\is_array($hits));
        foreach ($hits as $hit) {
            \assert(\is_array($hit));
            self::assertTrue($hit['enabled'] ?? false);
        }
    }

    #[Test]
    public function unauthenticatedSearchReturns401(): void
    {
        static::createClient()->request('GET', '/api/search/products?q=anything');
        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function searchObjectsScopedToObjectTypeId(): void
    {
        $this->seedProduct('SCOPED-OT-1', ['brand' => 'TestBrand']);
        $this->forceReindex(ObjectKind::Product);

        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        $type = self::getContainer()->get(ObjectTypeRepositoryInterface::class)
            ->findBuiltInByKind(ObjectKind::Product, $tenant);
        \assert(null !== $type);

        $client = $this->authenticatedClient();
        $body = $client->request('GET', '/api/search/objects?objectTypeId='.$type->getId()->toRfc4122())->toArray();

        self::assertArrayHasKey('hits', $body);
        self::assertGreaterThanOrEqual(1, $body['totalHits'] ?? 0);
    }

    #[Test]
    public function searchObjectsRejectsMissingObjectTypeId(): void
    {
        $client = $this->authenticatedClient();
        $client->request('GET', '/api/search/objects');
        self::assertResponseStatusCodeSame(400);
    }

    #[Test]
    public function searchObjectsRejectsInvalidUuid(): void
    {
        $client = $this->authenticatedClient();
        $client->request('GET', '/api/search/objects?objectTypeId=not-a-uuid');
        self::assertResponseStatusCodeSame(400);
    }

    #[Test]
    public function searchObjectsReturns404ForUnknownObjectType(): void
    {
        $client = $this->authenticatedClient();
        $client->request('GET', '/api/search/objects?objectTypeId=01923456-0000-7000-8000-000000000000');
        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function rollbackDropsCreatedDocsFromSearch(): void
    {
        // IMP2-2.4 — rollback v2 fixes the v1 ghost-documents bug: objects the
        // import CREATED must be dropped from Meilisearch on rollback (v1 deleted
        // the rows but left the search docs, so a stale hit lingered). Import two
        // objects, confirm they are searchable, roll the session back, then assert
        // they no longer appear in search results.
        $this->seedSkuName();

        $sessionId = $this->importProducts("sku;name\nGHOST-AAA;Ghostbrand Alpha\nGHOST-BBB;Ghostbrand Beta\n");

        $this->forceReindex(ObjectKind::Product);
        // The import + bulk reindex back-to-back leaves Meili briefly indexing;
        // give it a little extra slack before the precondition query so this
        // does not flake on a busy CI box (forceReindex's own wait covers the
        // lighter seedProduct path).
        usleep(900_000);

        $client = $this->authenticatedClient();
        $before = $client->request('GET', '/api/search/products?q=Ghostbrand')->toArray();
        self::assertGreaterThanOrEqual(2, $before['totalHits'] ?? 0, 'precondition: created docs are searchable');

        // Rollback over the API so kernel.terminate drains the reindex collector
        // (queueAllDeleted) into Meilisearch, dropping the created docs.
        $client->request('POST', \sprintf('/api/import-sessions/%s/rollback', $sessionId));
        self::assertResponseIsSuccessful();

        // Meilisearch is asynchronous — give the delete task time to settle.
        usleep(700_000);

        $after = $this->authenticatedClient()->request('GET', '/api/search/products?q=Ghostbrand')->toArray();
        self::assertSame(0, $after['totalHits'] ?? -1, 'rolled-back created objects must leave no ghost docs in search');
    }

    private function seedSkuName(): void
    {
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        self::getContainer()->get(TenantContext::class)->set($tenant);
        $product = self::getContainer()->get(ObjectTypeRepositoryInterface::class)
            ->findBuiltInByKind(ObjectKind::Product, $tenant);
        \assert($product instanceof \App\Catalog\Domain\Entity\ObjectType);

        $em = $this->em();
        $sku = new \App\Catalog\Domain\Entity\Attribute('sku', ['en' => 'SKU'], \App\Catalog\Domain\AttributeType::Text);
        $name = new \App\Catalog\Domain\Entity\Attribute('name', ['en' => 'Name'], \App\Catalog\Domain\AttributeType::Text);
        $em->persist($sku);
        $em->persist($name);
        $em->persist(new \App\Catalog\Domain\Entity\ObjectTypeAttribute($product, $sku, false, 1));
        $em->persist(new \App\Catalog\Domain\Entity\ObjectTypeAttribute($product, $name, false, 2));
        $em->flush();
    }

    private function importProducts(string $csv): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pim-search-rbk-').'.csv';
        file_put_contents($path, $csv);

        try {
            $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
            \assert($tenant instanceof Tenant);
            $type = self::getContainer()->get(ObjectTypeRepositoryInterface::class)
                ->findBuiltInByKind(ObjectKind::Product, $tenant);
            \assert(null !== $type);

            $client = $this->authenticatedClient();
            $client->request('POST', '/api/import-sessions', [
                'extra' => [
                    'parameters' => [
                        'target_object_type_id' => $type->getId()->toRfc4122(),
                        'mapping' => json_encode(['sku' => 'sku', 'name' => 'name'], JSON_THROW_ON_ERROR),
                        'mode' => 'UPSERT',
                    ],
                    'files' => ['file' => new \Symfony\Component\HttpFoundation\File\UploadedFile($path, 'search-rbk.csv', 'text/csv', null, true)],
                ],
            ]);
            self::assertResponseIsSuccessful();
            $decoded = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
            \assert(\is_array($decoded));
            $id = $decoded['id'] ?? null;

            return \is_scalar($id) ? (string) $id : '';
        } finally {
            @unlink($path);
        }
    }

    /**
     * @param array<string, scalar> $attributes
     */
    private function seedProduct(string $code, array $attributes = [], bool $enabled = true): void
    {
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);

        $context = self::getContainer()->get(TenantContext::class);
        $context->set($tenant);

        $type = self::getContainer()->get(ObjectTypeRepositoryInterface::class)
            ->findBuiltInByKind(ObjectKind::Product, $tenant);
        \assert(null !== $type);

        $repo = self::getContainer()->get(CatalogObjectRepositoryInterface::class);
        $object = new CatalogObject($type, $code);
        if (!$enabled) {
            $object->changeEnabled(false);
        }
        if ([] !== $attributes) {
            $wrapped = [];
            foreach ($attributes as $key => $value) {
                $wrapped[$key] = $value;
            }
            $object->updateAttributeIndex($wrapped);
        }
        $repo->save($object);
    }

    private function forceReindex(ObjectKind $kind): void
    {
        $indexer = self::getContainer()->get(BulkCatalogObjectIndexer::class);
        $indexer->reindex(kind: $kind);

        // Meilisearch is asynchronous — wait briefly for indexing to settle
        // so search queries see the new documents. 600 ms covers the small
        // batches this test pushes; CI may need a longer poll but we keep
        // it short to fail fast on real regressions.
        usleep(600_000);
    }
}
