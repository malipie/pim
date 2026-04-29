<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Identity\Application\RbacSeeder;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Rbac\RbacMatrix;
use App\Identity\Infrastructure\Doctrine\Repository\RoleRepository;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

use const JSON_THROW_ON_ERROR;

final class MeEndpointTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    protected static ?bool $alwaysBootKernel = true;

    private const string TENANT_CODE = 'demo';
    private const string ADMIN_EMAIL = 'admin@demo.localhost';
    private const string ADMIN_PASSWORD = 'changeme';

    protected function setUp(): void
    {
        parent::setUp();

        $em = $this->em();
        self::getContainer()->get(RbacSeeder::class)->seed();
        $superAdmin = self::getContainer()->get(RoleRepository::class)->findGlobalByCode(RbacMatrix::ROLE_SUPER_ADMIN);
        \assert(null !== $superAdmin);

        $tenant = new Tenant(self::TENANT_CODE, 'Demo Tenant');
        $em->persist($tenant);

        $hasher = $this->passwordHasher();
        $stub = new User($tenant, self::ADMIN_EMAIL, '');
        $admin = new User($tenant, self::ADMIN_EMAIL, $hasher->hashPassword($stub, self::ADMIN_PASSWORD));
        $admin->addRole($superAdmin);
        $em->persist($admin);
        $em->flush();
    }

    #[Test]
    public function meReturnsCurrentUser(): void
    {
        $client = static::createClient();
        $token = $this->loginAndExtractToken();

        $client->setDefaultOptions(['headers' => ['authorization' => 'Bearer '.$token]]);
        $response = $client->request('GET', '/api/auth/me');

        self::assertResponseIsSuccessful();
        $body = $response->toArray();
        self::assertSame(self::ADMIN_EMAIL, $body['email'] ?? null);
        self::assertIsString($body['id'] ?? null);
        self::assertIsArray($body['roles'] ?? null);
        self::assertContains('ROLE_SUPER_ADMIN', $body['roles']);
        self::assertIsArray($body['tenant'] ?? null);
        self::assertSame(self::TENANT_CODE, $body['tenant']['code'] ?? null);
        self::assertSame('Demo Tenant', $body['tenant']['name'] ?? null);
        self::assertArrayHasKey('last_login_at', $body);
    }

    #[Test]
    public function meWithoutTokenReturns401(): void
    {
        static::createClient()->request('GET', '/api/auth/me');
        self::assertResponseStatusCodeSame(401);
    }

    private function loginAndExtractToken(): string
    {
        $response = static::createClient()->request('POST', '/api/auth/login', [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode(
                ['email' => self::ADMIN_EMAIL, 'password' => self::ADMIN_PASSWORD],
                JSON_THROW_ON_ERROR,
            ),
        ]);

        $token = $response->toArray()['token'] ?? null;
        \assert(\is_string($token));

        return $token;
    }

    private function passwordHasher(): UserPasswordHasherInterface
    {
        return self::getContainer()->get(UserPasswordHasherInterface::class);
    }

    private function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }
}
