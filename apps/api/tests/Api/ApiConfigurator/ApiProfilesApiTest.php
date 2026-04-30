<?php

declare(strict_types=1);

namespace App\Tests\Api\ApiConfigurator;

use PHPUnit\Framework\Attributes\Test;

use const JSON_THROW_ON_ERROR;

/**
 * CRUD coverage for `/api/api_profiles` (#91 / 0.10.2).
 *
 * Asserts the AP4 wiring (resource XML + state processor + voter) is
 * end-to-end functional — POST → 201, PATCH → 200, DELETE → 204,
 * GET collection scoped by tenant filter.
 */
final class ApiProfilesApiTest extends ApiConfiguratorApiTestCase
{
    #[Test]
    public function postCreatesProfile(): void
    {
        $client = $this->authenticatedClient();
        $response = $client->request('POST', '/api/api_profiles', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'storefront',
                'name' => 'Storefront partner X',
                'outputFormat' => 'json_ld',
                'rateLimitPerHour' => 2000,
                'description' => 'Public storefront feed.',
            ], JSON_THROW_ON_ERROR),
        ]);

        self::assertResponseStatusCodeSame(201);
        $body = $response->toArray();
        self::assertSame('storefront', $body['code'] ?? null);
        self::assertSame('Storefront partner X', $body['name'] ?? null);
        self::assertSame('json_ld', $body['outputFormat'] ?? null);
        self::assertSame(2000, $body['rateLimitPerHour'] ?? null);
    }

    #[Test]
    public function postRejectsDuplicateCode(): void
    {
        $client = $this->authenticatedClient();
        $payload = json_encode([
            'code' => 'storefront',
            'name' => 'first',
            'outputFormat' => 'json_ld',
        ], JSON_THROW_ON_ERROR);

        $client->request('POST', '/api/api_profiles', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => $payload,
        ]);
        self::assertResponseStatusCodeSame(201);

        $client->request('POST', '/api/api_profiles', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'storefront',
                'name' => 'duplicate',
                'outputFormat' => 'json_ld',
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(409);
    }

    #[Test]
    public function postRejectsInvalidCode(): void
    {
        $client = $this->authenticatedClient();
        $client->request('POST', '/api/api_profiles', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'STOREFRONT WITH SPACES',
                'name' => 'invalid',
                'outputFormat' => 'json_ld',
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function patchUpdatesName(): void
    {
        $client = $this->authenticatedClient();
        $created = $client->request('POST', '/api/api_profiles', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'patchme',
                'name' => 'original',
                'outputFormat' => 'json_ld',
            ], JSON_THROW_ON_ERROR),
        ])->toArray();
        $id = $created['id'] ?? null;
        \assert(\is_string($id));

        $client->request('PATCH', '/api/api_profiles/'.$id, [
            'headers' => ['content-type' => 'application/merge-patch+json'],
            'body' => json_encode(['name' => 'renamed'], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(200);

        $reloaded = $client->request('GET', '/api/api_profiles/'.$id)->toArray();
        self::assertSame('renamed', $reloaded['name'] ?? null);
        self::assertSame('patchme', $reloaded['code'] ?? null);
    }

    #[Test]
    public function deleteRemovesProfile(): void
    {
        $client = $this->authenticatedClient();
        $created = $client->request('POST', '/api/api_profiles', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'tobedeleted',
                'name' => 'gone soon',
                'outputFormat' => 'json',
            ], JSON_THROW_ON_ERROR),
        ])->toArray();
        $id = $created['id'] ?? null;
        \assert(\is_string($id));

        $client->request('DELETE', '/api/api_profiles/'.$id);
        self::assertResponseStatusCodeSame(204);

        $client->request('GET', '/api/api_profiles/'.$id);
        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function getCollectionReturnsTenantProfiles(): void
    {
        $client = $this->authenticatedClient();
        foreach (['alpha', 'beta'] as $code) {
            $client->request('POST', '/api/api_profiles', [
                'headers' => ['content-type' => 'application/ld+json'],
                'body' => json_encode([
                    'code' => $code,
                    'name' => ucfirst($code),
                    'outputFormat' => 'json_ld',
                ], JSON_THROW_ON_ERROR),
            ]);
        }

        $body = $client->request('GET', '/api/api_profiles')->toArray();
        self::assertGreaterThanOrEqual(2, $body['totalItems'] ?? 0);
    }

    #[Test]
    public function unauthenticatedRequestsAre401(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/api_profiles');
        self::assertResponseStatusCodeSame(401);
    }
}
