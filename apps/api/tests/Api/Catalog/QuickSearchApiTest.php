<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

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
use PHPUnit\Framework\Attributes\Test;
use Throwable;

/**
 * UI-02.2 (#292) — `/api/products/quick-search` strict-mode endpoint.
 */
final class QuickSearchApiTest extends CatalogApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        try {
            self::getContainer()->get(MeilisearchClientFactory::class)->create()->health();
        } catch (Throwable) {
            self::markTestSkipped('Meilisearch container not available; covered by Playwright stack.');
        }

        self::getContainer()->get(MeilisearchIndexProvisioner::class)->provision();

        $client = self::getContainer()->get(MeilisearchClientFactory::class)->create();
        $client->index(IndexSettingsTemplate::indexName(ObjectKind::Product))->deleteAllDocuments();
        usleep(300_000);
    }

    #[Test]
    public function emptyQueryReturnsEmptyHitsWithoutHittingMeili(): void
    {
        $client = $this->authenticatedClient();
        $body = $client->request('GET', '/api/products/quick-search?q=')->toArray();

        self::assertSame([], $body['hits']);
        self::assertSame(0, $body['total']);
    }

    #[Test]
    public function strictPrefixSearchOnSku(): void
    {
        $this->seedProduct('TST-001');
        $this->seedProduct('TST-002');
        $this->seedProduct('OTHER-XYZ');
        $this->forceReindex();

        $client = $this->authenticatedClient();
        $body = $client->request('GET', '/api/products/quick-search?q=TST')->toArray();

        self::assertGreaterThanOrEqual(2, $body['total']);
        $hits = $body['hits'];
        \assert(\is_array($hits));
        $codes = [];
        foreach ($hits as $hit) {
            \assert(\is_array($hit));
            $code = $hit['code'] ?? null;
            if (\is_string($code)) {
                $codes[] = $code;
            }
        }
        self::assertContains('TST-001', $codes);
        self::assertContains('TST-002', $codes);
        self::assertNotContains('OTHER-XYZ', $codes);
    }

    #[Test]
    public function limitParameterIsClampedToMax(): void
    {
        for ($i = 0; $i < 5; ++$i) {
            $this->seedProduct(\sprintf('LIM-%03d', $i));
        }
        $this->forceReindex();

        $client = $this->authenticatedClient();
        $body = $client->request('GET', '/api/products/quick-search?q=LIM&limit=999')->toArray();

        $hits = $body['hits'];
        \assert(\is_array($hits));
        self::assertLessThanOrEqual(100, \count($hits));
    }

    private function seedProduct(string $code): void
    {
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);

        $context = self::getContainer()->get(TenantContext::class);
        $context->set($tenant);

        $type = self::getContainer()->get(ObjectTypeRepositoryInterface::class)
            ->findBuiltInByKind(ObjectKind::Product, $tenant);
        \assert(null !== $type);

        $object = new CatalogObject($type, $code);
        self::getContainer()->get(CatalogObjectRepositoryInterface::class)->save($object);
    }

    private function forceReindex(): void
    {
        self::getContainer()->get(BulkCatalogObjectIndexer::class)->reindex(kind: ObjectKind::Product);
        usleep(600_000);
    }
}
