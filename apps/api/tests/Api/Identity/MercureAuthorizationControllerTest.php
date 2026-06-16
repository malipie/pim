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
 * AUD-001 (#1573) — the Mercure subscriber-authorization endpoint mints
 * the `mercureAuthorization` cookie that the SPA presents before opening
 * an EventSource. This is the gate that closes the cross-tenant SSE leak:
 *
 *   1. anonymous callers get 401 — no cookie, so the (now non-anonymous)
 *      hub refuses every subscription;
 *   2. an authenticated caller's cookie carries a `mercure.subscribe`
 *      claim scoped to `tenant/{ownTenant}/…` ONLY — never the global
 *      `objects` topic that leaked every tenant's catalog events.
 */
final class MercureAuthorizationControllerTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    protected static ?bool $alwaysBootKernel = true;

    private const string TENANT_CODE = 'demo';
    private const string ADMIN_EMAIL = 'admin@demo.localhost';
    private const string ADMIN_PASSWORD = 'changeme';

    private string $tenantId = '';

    protected function setUp(): void
    {
        parent::setUp();

        self::getContainer()->get('limiter.auth_login')->create('127.0.0.1')->reset();

        $em = $this->em();
        self::getContainer()->get(RbacSeeder::class)->seed();
        $superAdmin = self::getContainer()->get(RoleRepositoryInterface::class)->findGlobalByCode(RbacMatrix::ROLE_SUPER_ADMIN);
        \assert(null !== $superAdmin);

        $tenant = new Tenant(self::TENANT_CODE, 'Demo Tenant');
        $em->persist($tenant);

        $hasher = $this->passwordHasher();
        $stub = new User($tenant, self::ADMIN_EMAIL, '');
        $admin = new User($tenant, self::ADMIN_EMAIL, $hasher->hashPassword($stub, self::ADMIN_PASSWORD));
        $admin->addRole($superAdmin);
        $em->persist($admin);
        $em->flush();

        $this->tenantId = $tenant->getId()->toRfc4122();
    }

    #[Test]
    public function anonymousCallReturns401(): void
    {
        static::createClient()->request('POST', '/api/mercure/authorization');
        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function authenticatedCallMintsTenantScopedSubscribeCookie(): void
    {
        $client = static::createClient();
        $token = $this->loginAndExtractToken();
        $client->setDefaultOptions(['headers' => ['authorization' => 'Bearer '.$token]]);

        $response = $client->request('POST', '/api/mercure/authorization');
        self::assertResponseIsSuccessful();

        $setCookie = $response->getHeaders()['set-cookie'] ?? [];
        $authCookie = null;
        foreach ($setCookie as $line) {
            if (str_starts_with($line, 'mercureAuthorization=')) {
                $authCookie = $line;
                break;
            }
        }
        self::assertNotNull($authCookie, 'Endpoint must emit a mercureAuthorization cookie.');

        $subscribe = $this->decodeSubscribeClaim($authCookie);
        self::assertNotEmpty($subscribe, 'JWT must carry a non-empty mercure.subscribe claim.');

        $prefix = 'https://pim.localhost/tenant/'.$this->tenantId.'/';
        foreach ($subscribe as $topic) {
            self::assertStringStartsWith(
                $prefix,
                $topic,
                \sprintf('Subscribe topic "%s" escapes the caller tenant scope.', $topic),
            );
        }

        // The exact globals that leaked before the fix must be absent.
        self::assertNotContains('https://pim.localhost/objects', $subscribe);
        self::assertNotContains('/objects', $subscribe);
    }

    /**
     * @return list<string>
     */
    private function decodeSubscribeClaim(string $setCookieLine): array
    {
        // mercureAuthorization=<jwt>; path=...; ...
        $value = substr($setCookieLine, \strlen('mercureAuthorization='));
        $jwt = explode(';', $value, 2)[0];
        $parts = explode('.', $jwt);
        self::assertCount(3, $parts, 'Cookie value must be a JWT.');

        $payload = json_decode($this->base64UrlDecode($parts[1]), true);
        \assert(\is_array($payload));
        $mercure = $payload['mercure'] ?? [];
        \assert(\is_array($mercure));
        $subscribe = $mercure['subscribe'] ?? [];
        \assert(\is_array($subscribe));

        $list = [];
        foreach ($subscribe as $topic) {
            \assert(\is_string($topic));
            $list[] = $topic;
        }

        return $list;
    }

    private function base64UrlDecode(string $data): string
    {
        $decoded = base64_decode(strtr($data, '-_', '+/'), true);
        \assert(false !== $decoded);

        return $decoded;
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
