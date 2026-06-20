<?php

declare(strict_types=1);

namespace App\Tests\Api\Identity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Identity\Application\RbacSeeder;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Rbac\RbacMatrix;
use App\Identity\Domain\Repository\RoleRepositoryInterface;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

use const JSON_THROW_ON_ERROR;

/**
 * Coverage for #48 (0.4.8) — auth_login rate limiter.
 *
 * Six POST requests inside one window must yield five normal responses
 * (200 / 401) plus a 429 with `Retry-After`. The limiter ticks on
 * every login attempt regardless of credentials — even successful
 * logins consume the budget so a stolen credential cannot re-arm it.
 */
final class AuthLoginRateLimitTest extends ApiTestCase
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

        // Rate limiter state persists across tests because the underlying
        // cache pool is filesystem-backed in dev/test. Reset the limiter
        // for the BrowserKit-default IP (`127.0.0.1`) so each test starts
        // with a fresh budget.
        self::getContainer()->get('limiter.auth_login')->create('127.0.0.1')->reset();

        $em = $this->em();
        self::getContainer()->get(RbacSeeder::class)->seed();
        $superAdmin = self::getContainer()->get(RoleRepositoryInterface::class)
            ->findGlobalByCode(RbacMatrix::ROLE_SUPER_ADMIN);
        \assert(null !== $superAdmin);

        $tenant = new Tenant(self::TENANT_CODE, 'Demo Tenant');
        $em->persist($tenant);

        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $stub = new User($tenant, self::ADMIN_EMAIL, '');
        $admin = new User($tenant, self::ADMIN_EMAIL, $hasher->hashPassword($stub, self::ADMIN_PASSWORD));
        $admin->addRole($superAdmin);
        $em->persist($admin);
        $em->flush();
    }

    #[Test]
    public function sixthLoginAttemptInWindowReturns429WithRetryAfter(): void
    {
        $client = static::createClient();

        // Five wrong-password attempts — each returns 401 (no token).
        for ($i = 1; $i <= 5; ++$i) {
            $client->request('POST', '/api/auth/login', [
                'headers' => ['content-type' => 'application/json'],
                'body' => json_encode(
                    ['email' => self::ADMIN_EMAIL, 'password' => 'wrong-'.$i],
                    JSON_THROW_ON_ERROR,
                ),
            ]);
            self::assertResponseStatusCodeSame(401, 'Attempt #'.$i.' must still hit Lexik (not the limiter).');
        }

        // Sixth attempt — the limiter rejects it before Lexik runs.
        $response = $client->request('POST', '/api/auth/login', [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode(
                ['email' => self::ADMIN_EMAIL, 'password' => 'wrong-6'],
                JSON_THROW_ON_ERROR,
            ),
        ]);

        self::assertResponseStatusCodeSame(429);
        // The 429 from the limiter MUST advertise when the budget refills.
        self::assertNotNull(
            $response->getHeaders(throw: false)['retry-after'][0] ?? null,
            'The throttled response must carry a Retry-After header.',
        );
    }

    #[Test]
    public function successfulLoginAlsoConsumesTheBudget(): void
    {
        $client = static::createClient();

        // Three successful logins (200) + three more wrong (401) =
        // six attempts; the seventh should be 429.
        for ($i = 0; $i < 3; ++$i) {
            $client->request('POST', '/api/auth/login', [
                'headers' => ['content-type' => 'application/json'],
                'body' => json_encode(
                    ['email' => self::ADMIN_EMAIL, 'password' => self::ADMIN_PASSWORD],
                    JSON_THROW_ON_ERROR,
                ),
            ]);
            self::assertResponseStatusCodeSame(200, 'Successful attempt #'.($i + 1));
        }

        for ($i = 0; $i < 2; ++$i) {
            $client->request('POST', '/api/auth/login', [
                'headers' => ['content-type' => 'application/json'],
                'body' => json_encode(
                    ['email' => self::ADMIN_EMAIL, 'password' => 'wrong'],
                    JSON_THROW_ON_ERROR,
                ),
            ]);
            self::assertResponseStatusCodeSame(401);
        }

        // Sixth attempt — limiter triggers regardless of credentials.
        $client->request('POST', '/api/auth/login', [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode(
                ['email' => self::ADMIN_EMAIL, 'password' => self::ADMIN_PASSWORD],
                JSON_THROW_ON_ERROR,
            ),
        ]);
        self::assertResponseStatusCodeSame(429);
    }

    private function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }
}
