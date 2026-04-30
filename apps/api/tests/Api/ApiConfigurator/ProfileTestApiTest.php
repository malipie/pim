<?php

declare(strict_types=1);

namespace App\Tests\Api\ApiConfigurator;

use App\ApiConfigurator\Domain\Entity\ApiProfile;
use App\ApiConfigurator\Domain\Enum\OutputFormat;
use App\ApiConfigurator\Domain\Repository\ApiProfileRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;

/**
 * Coverage for the per-profile test + OpenAPI endpoints (#95 / 0.10.6).
 *
 * The test endpoint reports the response contract (no live row), so
 * assertions stay on the contract shape: profile code echoed, output
 * format propagated, included attributes echoed in the sample shape.
 */
final class ProfileTestApiTest extends ApiConfiguratorApiTestCase
{
    #[Test]
    public function testEndpointEchoesProfileShape(): void
    {
        $this->seedProfile('storefront', ['name', 'brand']);

        $client = $this->authenticatedClient();
        $body = $client->request('GET', '/api/profiles/storefront/test')->toArray();

        self::assertSame('storefront', $body['profile'] ?? null);
        self::assertSame('json_ld', $body['outputFormat'] ?? null);
        $shape = $body['shape'] ?? [];
        \assert(\is_array($shape));
        self::assertArrayHasKey('attributes', $shape);
        $attributes = $shape['attributes'];
        \assert(\is_array($attributes));
        self::assertArrayHasKey('name', $attributes);
        self::assertArrayHasKey('brand', $attributes);
    }

    #[Test]
    public function testEndpointReturns404ForUnknownProfile(): void
    {
        $client = $this->authenticatedClient();
        $client->request('GET', '/api/profiles/nope/test');
        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function openapiEndpointStampsProfileMetadata(): void
    {
        $this->seedProfile('storefront', ['name']);

        $client = $this->authenticatedClient();
        $body = $client->request('GET', '/api/profiles/storefront/openapi.json')->toArray();

        $info = $body['info'] ?? [];
        \assert(\is_array($info));
        self::assertSame('storefront', $info['x-pim-profile'] ?? null);
        self::assertSame(['name'], $info['x-pim-included-attributes'] ?? null);
        $title = $info['title'] ?? '';
        self::assertIsString($title);
        self::assertStringContainsString('storefront', $title);
    }

    #[Test]
    public function unauthenticatedRequestIs401(): void
    {
        $this->seedProfile('storefront', []);
        $client = static::createClient();
        $client->request('GET', '/api/profiles/storefront/test');
        self::assertResponseStatusCodeSame(401);
    }

    /**
     * @param list<string> $included
     */
    private function seedProfile(string $code, array $included): void
    {
        $em = $this->em();
        $tenant = $em->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        self::getContainer()->get(TenantContext::class)->set($tenant);

        $profile = new ApiProfile(
            code: $code,
            name: ucfirst($code),
            outputFormat: OutputFormat::JSON_LD,
            includedAttributes: $included,
        );
        self::getContainer()->get(ApiProfileRepositoryInterface::class)->save($profile);
    }
}
