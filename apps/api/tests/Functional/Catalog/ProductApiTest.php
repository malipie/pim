<?php

declare(strict_types=1);

namespace App\Tests\Functional\Catalog;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Catalog\Domain\Entity\Product;
use App\Identity\Application\TenantContext;
use App\Identity\Domain\Entity\Tenant;
use App\Identity\Infrastructure\Doctrine\Repository\TenantRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

use const JSON_THROW_ON_ERROR;

/**
 * Functional API contract for /api/products.
 *
 * Each test runs against a fresh schema (Foundry ResetDatabase) and seeds a
 * single "demo" Tenant matching APP_DEFAULT_TENANT_CODE so the request
 * subscriber + Doctrine filter resolve a real tenant. Authentication wiring
 * lands in #4 (0.0.4); for Sprint 0 the env-fallback path is exercised here.
 */
final class ProductApiTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    /**
     * Required since api-platform/symfony 4.1: createClient() boots the kernel
     * once and this opt-in keeps the deprecation quiet under failOnDeprecation.
     */
    protected static ?bool $alwaysBootKernel = true;

    private const string TENANT_CODE = 'demo';

    protected function setUp(): void
    {
        parent::setUp();

        $em = $this->em();
        $em->persist(new Tenant(self::TENANT_CODE, 'Demo Tenant'));
        $em->flush();
    }

    #[Test]
    public function getCollectionReturnsHydraDocumentForCurrentTenant(): void
    {
        $this->seedProduct('SKU-LIST-001', 'Listed product');
        $this->seedProduct('SKU-LIST-002', 'Another listed product');

        static::createClient()->request('GET', '/api/products');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/ld+json; charset=utf-8');
        self::assertJsonContains([
            '@context' => '/api/contexts/Product',
            '@id' => '/api/products',
            '@type' => 'Collection',
            'totalItems' => 2,
        ]);
    }

    #[Test]
    public function postCreatesProductAndReturnsCreated(): void
    {
        $payload = [
            'sku' => 'WIDGET-001',
            'name' => 'Stainless steel widget',
            'description' => '12mm diameter, food-grade.',
            'brand' => 'Acme',
        ];

        $response = static::createClient()->request('POST', '/api/products', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonContains([
            '@type' => 'Product',
            'sku' => 'WIDGET-001',
            'name' => 'Stainless steel widget',
            'description' => '12mm diameter, food-grade.',
            'brand' => 'Acme',
        ]);

        $iri = $response->toArray()['@id'] ?? null;
        \assert(\is_string($iri));
        self::assertMatchesRegularExpression(
            '#^/api/products/[0-9a-f-]{36}$#',
            $iri,
            'POST must return an IRI for the created Product.',
        );
    }

    #[Test]
    public function postRejectsBlankSkuAndName(): void
    {
        static::createClient()->request('POST', '/api/products', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode(['sku' => '', 'name' => ''], JSON_THROW_ON_ERROR),
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains([
            '@type' => 'ConstraintViolation',
            'status' => 422,
        ]);
    }

    #[Test]
    public function getReturnsSingleProduct(): void
    {
        $product = $this->seedProduct('SKU-GET-001', 'Single product');

        static::createClient()->request('GET', '/api/products/'.$product->getId()->toRfc4122());

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            '@type' => 'Product',
            'sku' => 'SKU-GET-001',
            'name' => 'Single product',
        ]);
    }

    #[Test]
    public function patchUpdatesAllowedFieldsButLeavesSkuImmutable(): void
    {
        $product = $this->seedProduct('SKU-PATCH-001', 'Original name');

        static::createClient()->request('PATCH', '/api/products/'.$product->getId()->toRfc4122(), [
            'headers' => ['content-type' => 'application/merge-patch+json'],
            'body' => json_encode([
                'sku' => 'SKU-HIJACKED',
                'name' => 'Renamed product',
                'description' => 'Filled in via PATCH',
            ], JSON_THROW_ON_ERROR),
        ]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'sku' => 'SKU-PATCH-001',
            'name' => 'Renamed product',
            'description' => 'Filled in via PATCH',
        ]);
    }

    #[Test]
    public function getCollectionExposesCursorViewLinks(): void
    {
        // Three products are enough to assert the next/prev IRIs are emitted;
        // verifying the ordering math is the integration test job once we
        // ramp the corpus during the load-test ticket (#13 / 0.0.13).
        $this->seedProduct('SKU-CURSOR-001', 'A');
        $this->seedProduct('SKU-CURSOR-002', 'B');
        $this->seedProduct('SKU-CURSOR-003', 'C');

        $response = static::createClient()->request('GET', '/api/products?id[lt]=ffffffff-ffff-ffff-ffff-ffffffffffff');

        self::assertResponseIsSuccessful();
        $body = $response->toArray();
        self::assertArrayHasKey('view', $body, 'Cursor pagination must emit a PartialCollectionView.');
        $view = $body['view'];
        \assert(\is_array($view));
        self::assertSame('PartialCollectionView', $view['@type'] ?? null);
        self::assertArrayHasKey('next', $view);
    }

    private function seedProduct(string $sku, string $name): Product
    {
        $container = self::getContainer();

        $tenantRepository = $container->get(TenantRepository::class);
        $tenant = $tenantRepository->findByCode(self::TENANT_CODE);
        \assert($tenant instanceof Tenant, 'setUp must seed the demo tenant.');

        $tenantContext = $container->get(TenantContext::class);
        $tenantContext->set($tenant);

        $em = $this->em();
        $product = new Product($sku, $name);
        $em->persist($product);
        $em->flush();

        $tenantContext->clear();

        return $product;
    }

    private function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }
}
