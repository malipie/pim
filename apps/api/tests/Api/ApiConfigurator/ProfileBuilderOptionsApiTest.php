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

/**
 * APIC-P4-04 — `GET /api/api_profiles/builder_options` returns the tenant's
 * selectable attribute pool for the profile-builder. Asserts the response
 * contract + RBAC (the AttributeCatalogReader's data correctness is covered by
 * its own tests; a fresh tenant simply has an empty pool).
 */
final class ProfileBuilderOptionsApiTest extends ApiConfiguratorApiTestCase
{
    private const string URL = '/api/profiles/builder_options';

    #[Test]
    public function returnsAttributePoolEnvelope(): void
    {
        $body = $this->authenticatedClient()->request('GET', self::URL)->toArray();

        self::assertResponseStatusCodeSame(200);
        self::assertArrayHasKey('attributes', $body);
        self::assertIsArray($body['attributes']);
    }

    #[Test]
    public function unauthenticatedIs401(): void
    {
        static::createClient()->request('GET', self::URL);
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    #[Test]
    public function limitedUserIs403(): void
    {
        $this->limitedClient()->request('GET', self::URL);
        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    private function limitedClient(): Client
    {
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);

        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $email = 'limited-builder@demo.localhost';
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
