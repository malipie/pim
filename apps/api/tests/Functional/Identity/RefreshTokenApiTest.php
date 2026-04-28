<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Identity\Application\RbacSeeder;
use App\Identity\Application\RefreshTokenService;
use App\Identity\Domain\Entity\RefreshToken;
use App\Identity\Domain\Entity\Tenant;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Rbac\RbacMatrix;
use App\Identity\Infrastructure\Doctrine\Repository\RefreshTokenRepository;
use App\Identity\Infrastructure\Doctrine\Repository\RoleRepository;
use App\Identity\Presentation\AuthCookieFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\BrowserKit\Cookie as BrowserKitCookie;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

use const JSON_THROW_ON_ERROR;

/**
 * Functional contract for ticket #28 — refresh token rotation, theft detection,
 * /me endpoint, and the real logout flow.
 */
final class RefreshTokenApiTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    protected static ?bool $alwaysBootKernel = true;

    private const string TENANT_CODE = 'demo';
    private const string ADMIN_EMAIL = 'admin@demo.localhost';
    private const string ADMIN_PASSWORD = 'changeme';
    private const string COOKIE_NAME = AuthCookieFactory::COOKIE_NAME_DEFAULT;

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
        $admin = new User(
            $tenant,
            self::ADMIN_EMAIL,
            $hasher->hashPassword($stub, self::ADMIN_PASSWORD),
        );
        $admin->addRole($superAdmin);
        $em->persist($admin);
        $em->flush();
    }

    #[Test]
    public function loginIssuesAccessTokenAndRefreshCookie(): void
    {
        $client = static::createClient();
        $this->postLogin($client);

        self::assertResponseIsSuccessful();
        $cookie = $this->readRefreshCookie($client);
        self::assertNotNull($cookie, 'Refresh token cookie missing from login response.');
        self::assertTrue($cookie->isHttpOnly(), 'Refresh cookie must be HttpOnly.');
        self::assertSame('strict', $cookie->getSameSite());
        self::assertSame('/api/auth', $cookie->getPath());
    }

    #[Test]
    public function loginRecordsLastLoginAt(): void
    {
        $client = static::createClient();
        $this->postLogin($client);
        self::assertResponseIsSuccessful();

        $this->em()->clear();
        $admin = $this->em()->getRepository(User::class)->findOneBy(['email' => self::ADMIN_EMAIL]);
        \assert(null !== $admin);
        self::assertNotNull($admin->getLastLoginAt(), 'last_login_at should be stamped on success.');
    }

    #[Test]
    public function refreshRotatesTokens(): void
    {
        $client = static::createClient();
        $this->postLogin($client);
        $first = $this->readRefreshCookie($client);
        \assert(null !== $first);

        $client->request('POST', '/api/auth/refresh');
        self::assertResponseIsSuccessful();

        $body = $client->getResponse()?->toArray();
        self::assertIsArray($body);
        self::assertArrayHasKey('token', $body);

        $second = $this->readRefreshCookie($client);
        \assert(null !== $second);
        self::assertNotSame($first->getValue(), $second->getValue(), 'Refresh cookie value must rotate.');

        $em = $this->em();
        $em->clear();
        /** @var RefreshTokenRepository $tokens */
        $tokens = self::getContainer()->get(RefreshTokenRepository::class);
        $oldDb = $tokens->findByHash($this->hash($first->getValue()));
        \assert(null !== $oldDb);
        self::assertNotNull($oldDb->getUsedAt(), 'Rotated token must be marked used.');
    }

    #[Test]
    public function refreshWithReusedTokenRevokesEntireFamily(): void
    {
        $client = static::createClient();
        $this->postLogin($client);
        $first = $this->readRefreshCookie($client);
        \assert(null !== $first);

        // Legitimate first rotation — first → second.
        $client->request('POST', '/api/auth/refresh');
        self::assertResponseIsSuccessful();

        // Replay the very first cookie (theft scenario).
        $jar = $client->getKernelBrowser()->getCookieJar();
        $jar->clear();
        $jar->set(new BrowserKitCookie(self::COOKIE_NAME, $first->getValue(), null, '/api/auth'));
        $client->request('POST', '/api/auth/refresh');
        self::assertResponseStatusCodeSame(401);
        self::assertResponseHeaderSame('content-type', 'application/problem+json; charset=utf-8');
        $body = $client->getResponse()?->toArray(throw: false);
        self::assertSame('reused', $body['reason'] ?? null);

        $this->em()->clear();
        /** @var RefreshTokenRepository $tokens */
        $tokens = self::getContainer()->get(RefreshTokenRepository::class);
        $reusedRow = $tokens->findByHash($this->hash($first->getValue()));
        \assert(null !== $reusedRow);
        $familyId = $reusedRow->getFamilyId();
        $family = $this->em()->getRepository(RefreshToken::class)->findBy(['familyId' => $familyId]);
        self::assertGreaterThanOrEqual(2, \count($family));
        foreach ($family as $token) {
            self::assertNotNull(
                $token->getRevokedAt(),
                \sprintf('Token %s in reused family must be revoked.', $token->getId()->toRfc4122()),
            );
        }
    }

    #[Test]
    public function refreshWithExpiredTokenReturns401(): void
    {
        $client = static::createClient();
        $this->postLogin($client);
        $cookie = $this->readRefreshCookie($client);
        \assert(null !== $cookie);

        $em = $this->em();
        /** @var RefreshTokenRepository $tokens */
        $tokens = self::getContainer()->get(RefreshTokenRepository::class);
        $row = $tokens->findByHash($this->hash($cookie->getValue()));
        \assert(null !== $row);
        $em->getConnection()->executeStatement(
            'UPDATE refresh_tokens SET expires_at = :past WHERE id = :id',
            ['past' => '1999-01-01 00:00:00', 'id' => $row->getId()->toRfc4122()],
        );
        $em->clear();

        $client->request('POST', '/api/auth/refresh');
        self::assertResponseStatusCodeSame(401);
        $body = $client->getResponse()?->toArray(throw: false);
        self::assertSame('expired', $body['reason'] ?? null);
    }

    #[Test]
    public function refreshWithMissingCookieReturns401(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/auth/refresh');
        self::assertResponseStatusCodeSame(401);
        $body = $client->getResponse()?->toArray(throw: false);
        self::assertSame('missing', $body['reason'] ?? null);
    }

    #[Test]
    public function refreshWithUnknownCookieValueReturns401(): void
    {
        $client = static::createClient();
        $jar = $client->getKernelBrowser()->getCookieJar();
        $jar->set(new BrowserKitCookie(self::COOKIE_NAME, 'not-a-real-token-value', null, '/api/auth'));

        $client->request('POST', '/api/auth/refresh');
        self::assertResponseStatusCodeSame(401);
        $body = $client->getResponse()?->toArray(throw: false);
        self::assertSame('invalid', $body['reason'] ?? null);
    }

    #[Test]
    public function logoutRevokesRefreshTokenAndClearsCookie(): void
    {
        $client = static::createClient();
        $token = $this->postLogin($client);
        $cookie = $this->readRefreshCookie($client);
        \assert(null !== $cookie);

        $client->setDefaultOptions(['headers' => ['authorization' => 'Bearer '.$token]]);
        $client->request('POST', '/api/auth/logout');
        self::assertResponseStatusCodeSame(204);

        $cleared = $this->readRefreshCookie($client);
        // After Max-Age=0 BrowserKit drops the cookie from the jar entirely.
        self::assertNull($cleared, 'Refresh cookie must be cleared on logout.');

        $this->em()->clear();
        /** @var RefreshTokenRepository $tokens */
        $tokens = self::getContainer()->get(RefreshTokenRepository::class);
        $row = $tokens->findByHash($this->hash($cookie->getValue()));
        \assert(null !== $row);
        self::assertNotNull($row->getRevokedAt(), 'Active refresh token must be revoked on logout.');
    }

    #[Test]
    public function logoutWithoutCookieIsIdempotent(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/auth/logout');
        // /api/auth/logout sits on the api firewall; without a Bearer it is 401.
        self::assertResponseStatusCodeSame(401);

        // With a valid Bearer but no refresh cookie -> 204.
        $client = static::createClient();
        $token = $this->postLogin($client);
        $client->getKernelBrowser()->getCookieJar()->clear();
        $client->setDefaultOptions(['headers' => ['authorization' => 'Bearer '.$token]]);
        $client->request('POST', '/api/auth/logout');
        self::assertResponseStatusCodeSame(204);
    }

    private function postLogin(Client $client): string
    {
        $response = $client->request('POST', '/api/auth/login', [
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

    private function readRefreshCookie(Client $client): ?BrowserKitCookie
    {
        return $client->getKernelBrowser()->getCookieJar()->get(self::COOKIE_NAME, '/api/auth');
    }

    private function hash(string $raw): string
    {
        /** @var RefreshTokenService $service */
        $service = self::getContainer()->get(RefreshTokenService::class);

        return $service->hash($raw);
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
