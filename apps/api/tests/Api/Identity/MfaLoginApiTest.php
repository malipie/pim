<?php

declare(strict_types=1);

namespace App\Tests\Api\Identity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Identity\Application\RbacSeeder;
use App\Identity\Application\TotpEnrolmentService;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Rbac\RbacMatrix;
use App\Identity\Domain\Repository\RoleRepositoryInterface;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use OTPHP\TOTP;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

use const JSON_THROW_ON_ERROR;

/**
 * #1141 — second-factor enforcement on `POST /api/auth/login`.
 *
 * Before this ticket, enabling TOTP had no effect on login: the password
 * step issued a JWT directly. Now a user with active TOTP is parked behind
 * a challenge and must redeem `POST /api/auth/2fa/login` with a TOTP or
 * backup code to get the JWT.
 */
final class MfaLoginApiTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    protected static ?bool $alwaysBootKernel = true;

    private const string TENANT_CODE = 'demo';
    private const string MFA_EMAIL = 'mfa@demo.localhost';
    private const string PLAIN_EMAIL = 'plain@demo.localhost';
    private const string PASSWORD = 'changeme';

    private string $totpSecret = '';
    private string $backupCode = '';

    protected function setUp(): void
    {
        parent::setUp();
        self::getContainer()->get('limiter.auth_login')->create('127.0.0.1')->reset();

        $em = $this->em();
        self::getContainer()->get(RbacSeeder::class)->seed();
        $superAdmin = self::getContainer()->get(RoleRepositoryInterface::class)
            ->findGlobalByCode(RbacMatrix::ROLE_SUPER_ADMIN);
        \assert(null !== $superAdmin);

        $tenant = new Tenant(self::TENANT_CODE, 'Demo Tenant');
        $em->persist($tenant);

        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);

        $mfaUser = new User($tenant, self::MFA_EMAIL, '');
        $mfaUser = new User($tenant, self::MFA_EMAIL, $hasher->hashPassword($mfaUser, self::PASSWORD));
        $mfaUser->addRole($superAdmin);
        $em->persist($mfaUser);

        $plain = new User($tenant, self::PLAIN_EMAIL, '');
        $plain = new User($tenant, self::PLAIN_EMAIL, $hasher->hashPassword($plain, self::PASSWORD));
        $plain->addRole($superAdmin);
        $em->persist($plain);
        $em->flush();

        // Activate TOTP for the MFA user via the service (mirrors enrol → confirm).
        $enrolment = self::getContainer()->get(TotpEnrolmentService::class);
        $enrol = $enrolment->enrol($mfaUser);
        $secret = $enrol['secret'];
        \assert('' !== $secret);
        $enrolment->confirm($mfaUser, TOTP::createFromSecret($secret)->now());

        $this->totpSecret = $secret;
        $this->backupCode = $enrol['backup_codes'][0];
    }

    #[Test]
    public function loginWithActiveMfaReturnsChallengeNotToken(): void
    {
        $client = static::createClient();
        $body = $this->passwordStep($client);

        self::assertTrue($body['mfa_required'] ?? false);
        self::assertArrayHasKey('mfa_token', $body);
        self::assertArrayNotHasKey('token', $body);
    }

    #[Test]
    public function secondFactorWithValidTotpIssuesJwt(): void
    {
        $client = static::createClient();
        $mfaToken = $this->mfaTokenFrom($this->passwordStep($client));

        $response = $client->request('POST', '/api/auth/2fa/login', [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode(
                ['mfa_token' => $mfaToken, 'code' => $this->totpNow()],
                JSON_THROW_ON_ERROR,
            ),
        ]);

        self::assertResponseIsSuccessful();
        $out = $response->toArray();
        self::assertArrayHasKey('token', $out);
        $jwt = $out['token'];
        self::assertIsString($jwt);
        // A real, well-formed JWT (header.payload.signature) was minted —
        // same path as a password-only login.
        self::assertSame(2, substr_count($jwt, '.'));
    }

    private function totpNow(): string
    {
        $secret = $this->totpSecret;
        \assert('' !== $secret);

        return TOTP::createFromSecret($secret)->now();
    }

    #[Test]
    public function secondFactorRejectsWrongCode(): void
    {
        $client = static::createClient();
        $mfaToken = $this->mfaTokenFrom($this->passwordStep($client));

        $client->request('POST', '/api/auth/2fa/login', [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode(['mfa_token' => $mfaToken, 'code' => '000000'], JSON_THROW_ON_ERROR),
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function secondFactorAcceptsBackupCode(): void
    {
        $client = static::createClient();
        $mfaToken = $this->mfaTokenFrom($this->passwordStep($client));

        $response = $client->request('POST', '/api/auth/2fa/login', [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode(['mfa_token' => $mfaToken, 'code' => $this->backupCode], JSON_THROW_ON_ERROR),
        ]);

        self::assertResponseIsSuccessful();
        self::assertArrayHasKey('token', $response->toArray());
    }

    #[Test]
    public function userWithoutMfaLogsInDirectly(): void
    {
        $client = static::createClient();
        $response = $client->request('POST', '/api/auth/login', [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode(
                ['email' => self::PLAIN_EMAIL, 'password' => self::PASSWORD],
                JSON_THROW_ON_ERROR,
            ),
        ]);

        self::assertResponseIsSuccessful();
        $out = $response->toArray();
        self::assertArrayHasKey('token', $out);
        self::assertArrayNotHasKey('mfa_required', $out);
    }

    /**
     * @return array<int|string, mixed>
     */
    private function passwordStep(Client $client): array
    {
        $response = $client->request('POST', '/api/auth/login', [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode(
                ['email' => self::MFA_EMAIL, 'password' => self::PASSWORD],
                JSON_THROW_ON_ERROR,
            ),
        ]);
        self::assertResponseIsSuccessful();

        return $response->toArray();
    }

    /**
     * @param array<int|string, mixed> $body
     */
    private function mfaTokenFrom(array $body): string
    {
        $token = $body['mfa_token'] ?? null;
        \assert(\is_string($token) && '' !== $token);

        return $token;
    }

    private function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }
}
