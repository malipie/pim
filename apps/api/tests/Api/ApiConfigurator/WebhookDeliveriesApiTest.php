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
 * APIC-P4-06 — `/api/webhook_deliveries` read API (producer hub Webhooks tab).
 * Asserts the collection envelope + RBAC; deliveries are written only by the
 * sync/webhook engine, so a fresh tenant returns an empty (but well-formed)
 * collection.
 */
final class WebhookDeliveriesApiTest extends ApiConfiguratorApiTestCase
{
    private const string URL = '/api/webhook_deliveries';

    #[Test]
    public function returnsCollectionEnvelope(): void
    {
        $body = $this->authenticatedClient()->request('GET', self::URL)->toArray();

        self::assertResponseStatusCodeSame(200);
        self::assertArrayHasKey('totalItems', $body);
        self::assertArrayHasKey('member', $body);
        self::assertIsArray($body['member']);
    }

    #[Test]
    public function filterByProfileIsAccepted(): void
    {
        $this->authenticatedClient()->request(
            'GET',
            self::URL.'?profile=01234567-1234-7000-8000-000000000000',
        );
        self::assertResponseStatusCodeSame(200);
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
        $email = 'limited-webhooks@demo.localhost';
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
