<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Catalog\Domain\Entity\Product;
use App\Identity\Domain\Entity\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

use const JSON_THROW_ON_ERROR;

/**
 * Smoke test for ticket #12 (0.0.12) — proves the Doctrine-level TenantFilter
 * actually scopes reads to the current tenant and that the Postgres unique
 * index does not accidentally leak the existence of cross-tenant rows.
 *
 * The "current tenant" in Sprint-0 (pre-auth) is resolved by
 * CurrentTenantProvider from APP_DEFAULT_TENANT_CODE; we flip that env var
 * between requests, shutting the kernel between flips so the freshly booted
 * container resolves the new value. Once #4 (0.0.4) lands the kernel reboots
 * become unnecessary — the test can authenticate as either tenant's user.
 *
 * Native-SQL bypass test is intentional: it documents that this filter is the
 * application-layer boundary only. Postgres RLS in phase 1 (sekcja 11.1a
 * architektury) closes that gap; until then bulk paths must apply tenant scope
 * in code.
 */
final class TenantIsolationTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    protected static ?bool $alwaysBootKernel = true;

    private const string TENANT_A_CODE = 'tenant-alpha';
    private const string TENANT_B_CODE = 'tenant-bravo';
    private const int PRODUCTS_PER_TENANT = 5;

    private Uuid $tenantBProductId;

    protected function setUp(): void
    {
        parent::setUp();

        $em = $this->em();

        $tenantA = new Tenant(self::TENANT_A_CODE, 'Alpha Industries');
        $tenantB = new Tenant(self::TENANT_B_CODE, 'Bravo Holdings');
        $em->persist($tenantA);
        $em->persist($tenantB);
        $em->flush();

        for ($i = 1; $i <= self::PRODUCTS_PER_TENANT; ++$i) {
            $alphaProduct = new Product(\sprintf('ALPHA-%03d', $i), \sprintf('Alpha #%d', $i));
            $alphaProduct->assignTenant($tenantA);
            $em->persist($alphaProduct);

            $bravoProduct = new Product(\sprintf('BRAVO-%03d', $i), \sprintf('Bravo #%d', $i));
            $bravoProduct->assignTenant($tenantB);
            $em->persist($bravoProduct);

            if (1 === $i) {
                $this->tenantBProductId = $bravoProduct->getId();
            }
        }
        $em->flush();

        // The test below boots a fresh kernel through createClient(); shutting
        // down here means the next boot picks up our APP_DEFAULT_TENANT_CODE
        // override instead of a cached value from the seed-phase boot.
        static::ensureKernelShutdown();
    }

    #[Test]
    public function listingProductsAsTenantAReturnsOnlyTenantARecords(): void
    {
        $this->withTenant(self::TENANT_A_CODE);

        $response = static::createClient()->request('GET', '/api/products');

        self::assertResponseIsSuccessful();
        $body = $response->toArray();
        self::assertSame(self::PRODUCTS_PER_TENANT, $body['totalItems']);

        $members = $body['member'];
        \assert(\is_array($members));
        foreach ($members as $member) {
            \assert(\is_array($member));
            $sku = $member['sku'] ?? null;
            self::assertIsString($sku);
            self::assertStringStartsWith(
                'ALPHA-',
                $sku,
                'Tenant A must not see any tenant B SKU in /api/products.',
            );
        }
    }

    #[Test]
    public function fetchingTenantBProductAsTenantAReturns404(): void
    {
        $this->withTenant(self::TENANT_A_CODE);

        static::createClient()->request(
            'GET',
            '/api/products/'.$this->tenantBProductId->toRfc4122(),
        );

        // 404, not 403 — the filter hides existence, it does not reveal a
        // forbidden resource. Returning 403 would itself be a side-channel
        // leak that the row exists in another tenant.
        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function patchingTenantBProductAsTenantAReturns404(): void
    {
        $this->withTenant(self::TENANT_A_CODE);

        static::createClient()->request(
            'PATCH',
            '/api/products/'.$this->tenantBProductId->toRfc4122(),
            [
                'headers' => ['content-type' => 'application/merge-patch+json'],
                'body' => json_encode(['name' => 'Hijack attempt'], JSON_THROW_ON_ERROR),
            ],
        );

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function nativeSqlBypassesTheDoctrineFilterByDesign(): void
    {
        // This test documents the boundary: TenantFilter is an application-layer
        // mechanism. Code that runs raw SQL via DBAL still sees every tenant's
        // rows. Phase-1 RLS closes the gap (sekcja 11.1a architektury); for now
        // the runbook for bulk operations must apply tenant scope in code.
        $em = $this->em();
        $connection = $em->getConnection();

        $count = $connection->fetchOne('SELECT COUNT(*) FROM products');
        \assert(\is_string($count) || \is_int($count));

        self::assertSame(
            2 * self::PRODUCTS_PER_TENANT,
            (int) $count,
            'Raw DBAL queries must still see every tenant row — RLS in phase 1 will scope them.',
        );
    }

    private function withTenant(string $code): void
    {
        $_ENV['APP_DEFAULT_TENANT_CODE'] = $code;
        $_SERVER['APP_DEFAULT_TENANT_CODE'] = $code;
        putenv('APP_DEFAULT_TENANT_CODE='.$code);
    }

    private function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }
}
