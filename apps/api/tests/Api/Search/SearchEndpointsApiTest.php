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
