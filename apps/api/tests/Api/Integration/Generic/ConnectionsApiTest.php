<?php

declare(strict_types=1);

namespace App\Tests\Api\Integration\Generic;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Identity\Domain\Entity\User;
use App\Tests\Api\ApiConfigurator\ApiConfiguratorApiTestCase;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use const JSON_THROW_ON_ERROR;

/**
 * APIC-P1-06 — CRUD coverage for `/api/connections` (ApiResource + processor +
 * voter + serializer masking). Reuses the ApiConfigurator RBAC scaffold.
 */
final class ConnectionsApiTest extends ApiConfiguratorApiTestCase
{
    private const string LD_JSON = 'application/ld+json';
    private const string MERGE_PATCH = 'application/merge-patch+json';

    #[Test]
    public function postCreatesConnectionAndNeverLeaksCredentials(): void
    {
        $body = $this->create('idosell', [
            'authType' => 'api_key',
            'credentials' => ['header' => 'X-Api-Key', 'value' => 's3cr3t'],
            'rateLimitHint' => 120,
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('idosell', $body['code'] ?? null);
        self::assertSame('api_key', $body['authType'] ?? null);
        self::assertSame('draft', $body['status'] ?? null);
        self::assertSame(120, $body['rateLimitHint'] ?? null);
        // Secrets must never round-trip out.
        self::assertArrayNotHasKey('credentials', $body);
        self::assertArrayNotHasKey('credentialsCiphertext', $body);
        self::assertArrayNotHasKey('credentialsKeyVersion', $body);
    }

    #[Test]
    public function postRejectsDuplicateCode(): void
    {
        $this->create('idosell');
        self::assertResponseStatusCodeSame(201);

        $this->create('idosell');
        self::assertResponseStatusCodeSame(409);
    }

    #[Test]
    public function postRejectsInvalidCode(): void
    {
        $this->create('UPPER SPACES');
        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function postRejectsNonHttpBaseUrl(): void
    {
        $this->authenticatedClient()->request('POST', '/api/connections', [
            'headers' => ['content-type' => self::LD_JSON],
            'body' => json_encode([
                'code' => 'badurl',
                'name' => 'Bad URL',
                'baseUrl' => 'file:///etc/passwd',
            ], JSON_THROW_ON_ERROR),
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    #[Test]
    public function patchUpdatesNameAndPausesConnection(): void
    {
        $id = $this->create('idosell')['id'] ?? null;
        \assert(\is_string($id));

        $client = $this->authenticatedClient();
        $body = $client->request('PATCH', '/api/connections/'.$id, [
            'headers' => ['content-type' => self::MERGE_PATCH],
            'body' => json_encode(['name' => 'IdoSell PL', 'status' => 'paused'], JSON_THROW_ON_ERROR),
        ])->toArray();

        self::assertResponseStatusCodeSame(200);
        self::assertSame('IdoSell PL', $body['name'] ?? null);
        self::assertSame('paused', $body['status'] ?? null);
    }

    #[Test]
    public function deleteRemovesConnection(): void
    {
        $id = $this->create('idosell')['id'] ?? null;
        \assert(\is_string($id));

        $client = $this->authenticatedClient();
        $client->request('DELETE', '/api/connections/'.$id);
        self::assertResponseStatusCodeSame(204);

        $client->request('GET', '/api/connections/'.$id);
        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function unauthenticatedRequestIs401(): void
    {
        static::createClient()->request('GET', '/api/connections');
        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function userWithoutIntegrationsPermissionIs403(): void
    {
        $this->limitedClient()->request('GET', '/api/connections');
        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<array-key, mixed>
     */
    private function create(string $code, array $overrides = []): array
    {
        $payload = array_merge([
            'code' => $code,
            'name' => ucfirst($code),
            'baseUrl' => 'https://api.idosell.com',
            'authType' => 'none',
        ], $overrides);

        return $this->authenticatedClient()->request('POST', '/api/connections', [
            'headers' => ['content-type' => self::LD_JSON],
            'body' => json_encode($payload, JSON_THROW_ON_ERROR),
        ])->toArray(false);
    }

    private function limitedClient(): Client
    {
        $tenant = $this->em()->getRepository(\App\Shared\Domain\Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof \App\Shared\Domain\Tenant);

        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $email = 'limited@demo.localhost';
        $stub = new User($tenant, $email, '', ['ROLE_USER']);
        $user = new User($tenant, $email, $hasher->hashPassword($stub, 'changeme'), ['ROLE_USER']);
        $em = $this->em();
        $em->persist($user);
        $em->flush();

        $jwt = self::getContainer()->get(JWTTokenManagerInterface::class)->create($user);

        $client = static::createClient();
        $client->setDefaultOptions(['headers' => ['authorization' => 'Bearer '.$jwt]]);

        return $client;
    }
}
