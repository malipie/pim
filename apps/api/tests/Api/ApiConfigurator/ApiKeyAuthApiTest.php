<?php

declare(strict_types=1);

namespace App\Tests\Api\ApiConfigurator;

use App\ApiConfigurator\Application\ApiKeyGenerator;
use App\ApiConfigurator\Domain\Entity\ApiKey;
use App\ApiConfigurator\Domain\Entity\ApiProfile;
use App\ApiConfigurator\Domain\Enum\OutputFormat;
use App\ApiConfigurator\Domain\Repository\ApiKeyRepositoryInterface;
use App\ApiConfigurator\Domain\Repository\ApiProfileRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;

use const JSON_THROW_ON_ERROR;

/**
 * End-to-end coverage for X-API-Key authentication (#94 / 0.10.5).
 *
 * Mints a real key + profile through the application services so the
 * Argon2id verify + tenant resolution happens through the same path
 * production uses. Each test boots a fresh kernel.
 */
final class ApiKeyAuthApiTest extends ApiConfiguratorApiTestCase
{
    #[Test]
    public function getCollectionAuthenticatesWithApiKey(): void
    {
        $rawKey = $this->seedKeyWithScope('storefront');

        $client = static::createClient();
        $response = $client->request('GET', '/api/api_profiles', [
            'headers' => ['X-API-Key' => $rawKey],
        ]);
        self::assertResponseIsSuccessful();
        $body = $response->toArray();
        self::assertGreaterThanOrEqual(1, $body['totalItems'] ?? 0);
    }

    #[Test]
    public function unknownKeyIsRejected(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/api_profiles', [
            'headers' => ['X-API-Key' => 'pim_live_DEADBEEFDEADBEEFDEADBEEFDEADBEEF12'],
        ]);
        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function revokedKeyIsRejected(): void
    {
        $rawKey = $this->seedKeyWithScope('storefront', revoked: true);

        $client = static::createClient();
        $client->request('GET', '/api/api_profiles', [
            'headers' => ['X-API-Key' => $rawKey],
        ]);
        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function apiKeyCannotPerformWriteOperations(): void
    {
        $rawKey = $this->seedKeyWithScope('storefront');

        $client = static::createClient();
        $client->request('POST', '/api/api_profiles', [
            'headers' => [
                'X-API-Key' => $rawKey,
                'content-type' => 'application/ld+json',
            ],
            'body' => json_encode([
                'code' => 'newprofile',
                'name' => 'Should Not Create',
                'outputFormat' => 'json_ld',
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(403);
    }

    private function seedKeyWithScope(string $profileCode, bool $revoked = false): string
    {
        $em = $this->em();
        $tenant = $em->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        self::getContainer()->get(TenantContext::class)->set($tenant);

        $profile = new ApiProfile($profileCode, ucfirst($profileCode), OutputFormat::JSON_LD);
        self::getContainer()->get(ApiProfileRepositoryInterface::class)->save($profile);

        $generator = self::getContainer()->get(ApiKeyGenerator::class);
        $generated = $generator->generate();
        $apiKey = new ApiKey(
            keyHash: $generated->keyHash,
            keyPrefix: $generated->keyPrefix,
            name: 'demo-key',
            scopes: [$profileCode],
        );
        if ($revoked) {
            $apiKey->revoke(new DateTimeImmutable());
        }
        self::getContainer()->get(ApiKeyRepositoryInterface::class)->save($apiKey);

        return $generated->rawKey;
    }
}
