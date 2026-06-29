<?php

declare(strict_types=1);

namespace App\Tests\Api\ApiConfigurator;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Identity\Domain\Entity\User;
use App\Shared\Domain\Tenant;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use const JSON_THROW_ON_ERROR;

/**
 * APIC-P4-03 — `GET /api/docs/profile/{id}.jsonopenapi` returns the OpenAPI
 * document scoped to one profile (profile-scope metadata + catalog data paths
 * only).
 */
final class ProfileOpenApiApiTest extends ApiConfiguratorApiTestCase
{
    #[Test]
    public function exportsProfileScopedOpenApi(): void
    {
        $id = $this->createProfile();

        $body = $this->authenticatedClient()->request('GET', "/api/docs/profile/{$id}.jsonopenapi")->toArray();

        self::assertResponseStatusCodeSame(200);
        self::assertArrayHasKey('openapi', $body);
        $info = $body['info'] ?? [];
        self::assertIsArray($info);
        self::assertSame('storefront', $info['x-pim-profile'] ?? null);
        self::assertSame(['name', 'sku'], $info['x-pim-included-attributes'] ?? null);

        // Only catalog data paths survive; auth/admin surfaces are dropped.
        $paths = $body['paths'] ?? [];
        self::assertIsArray($paths);
        foreach (array_keys($paths) as $path) {
            self::assertMatchesRegularExpression(
                '#^/api/(products|categories|assets|objects)#',
                (string) $path,
                \sprintf('Unexpected non-data path in profile OpenAPI: %s', (string) $path),
            );
        }
    }

    #[Test]
    public function unknownProfileIs404(): void
    {
        $this->authenticatedClient()->request(
            'GET',
            '/api/docs/profile/01234567-1234-7000-8000-000000000000.jsonopenapi',
        );
        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function unauthenticatedIs401(): void
    {
        $id = $this->createProfile();
        static::createClient()->request('GET', "/api/docs/profile/{$id}.jsonopenapi");
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    #[Test]
    public function limitedUserIs403(): void
    {
        $id = $this->createProfile();
        $this->limitedClient()->request('GET', "/api/docs/profile/{$id}.jsonopenapi");
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    private function createProfile(): string
    {
        $body = $this->authenticatedClient()->request('POST', '/api/api_profiles', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'storefront',
                'name' => 'Storefront',
                'outputFormat' => 'json_ld',
                'objectTypeIds' => [],
                'includedAttributes' => ['name', 'sku'],
            ], JSON_THROW_ON_ERROR),
        ])->toArray(false);

        $id = $body['id'] ?? null;
        \assert(\is_string($id));

        return $id;
    }

    private function limitedClient(): Client
    {
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);

        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $email = 'limited-openapi@demo.localhost';
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
