<?php

declare(strict_types=1);

namespace App\Tests\Api\Identity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

use const JSON_THROW_ON_ERROR;

/**
 * RBAC-P5-012 (#702) — coverage for the change-password endpoint.
 *
 * Invariants:
 *  - 401 without JWT (firewall),
 *  - 401 with wrong current_password (`UserPasswordHasher::isPasswordValid` mismatch),
 *  - 400 when new_password shorter than 12 characters,
 *  - 204 on success, login with the new password works, login with
 *    the old password fails.
 */
final class ChangePasswordControllerTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    protected static ?bool $alwaysBootKernel = true;

    private const string TENANT_CODE = 'demo';
    private const string EMAIL = 'admin@demo.localhost';
    private const string OLD_PASSWORD = 'changeme-old-12345';
    private const string NEW_PASSWORD = 'changeme-new-12345';

    protected function setUp(): void
    {
        parent::setUp();

        self::getContainer()->get('limiter.auth_login')->create('127.0.0.1')->reset();

        $em = $this->em();
        $tenant = new Tenant(self::TENANT_CODE, 'Demo Tenant');
        $em->persist($tenant);

        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $stub = new User($tenant, self::EMAIL, '');
        $user = new User($tenant, self::EMAIL, $hasher->hashPassword($stub, self::OLD_PASSWORD));
        $em->persist($user);
        $em->flush();
    }

    #[Test]
    public function noJwtReturns401(): void
    {
        static::createClient()->request('POST', '/api/me/change-password', [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'current_password' => self::OLD_PASSWORD,
                'new_password' => self::NEW_PASSWORD,
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function wrongCurrentPasswordReturns401(): void
    {
        $client = $this->clientFor(self::EMAIL);
        $client->request('POST', '/api/me/change-password', [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'current_password' => 'wrong-password-1234',
                'new_password' => self::NEW_PASSWORD,
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type', 'application/problem+json; charset=utf-8');
    }

    #[Test]
    public function shortNewPasswordReturns400(): void
    {
        $client = $this->clientFor(self::EMAIL);
        $client->request('POST', '/api/me/change-password', [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'current_password' => self::OLD_PASSWORD,
                'new_password' => 'short',
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(400);
    }

    #[Test]
    public function successReturns204AndOldPasswordNoLongerWorks(): void
    {
        $client = $this->clientFor(self::EMAIL);
        $client->request('POST', '/api/me/change-password', [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'current_password' => self::OLD_PASSWORD,
                'new_password' => self::NEW_PASSWORD,
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(204);

        // Old password rejected.
        static::createClient()->request('POST', '/api/auth/login', [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'email' => self::EMAIL,
                'password' => self::OLD_PASSWORD,
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(401);

        // New password accepted.
        static::createClient()->request('POST', '/api/auth/login', [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode([
                'email' => self::EMAIL,
                'password' => self::NEW_PASSWORD,
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(200);
    }

    private function clientFor(string $email): Client
    {
        $user = self::getContainer()->get(UserRepositoryInterface::class)->findByEmail($email);
        \assert(null !== $user);
        $jwt = self::getContainer()->get(JWTTokenManagerInterface::class)->create($user);
        $client = static::createClient();
        $client->setDefaultOptions(['headers' => ['authorization' => 'Bearer '.$jwt]]);

        return $client;
    }

    private function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }
}
