<?php

declare(strict_types=1);

namespace App\Tests\Api\Identity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;

use const JSON_THROW_ON_ERROR;

/**
 * Coverage for AUD-030 (W2-12) — rate limiter on
 * `POST /api/auth/password-reset/request`.
 *
 * The endpoint is PUBLIC_ACCESS and always-200 (account-enumeration
 * prevention), which made it a free spam / timing-oracle target before
 * AUD-030: an attacker could fire unbounded requests to enumerate which
 * emails trigger the mailer side-effect, or DoS the SMTP relay.
 *
 * Two limiters guard it now — a tight per-email window (5/15min, the
 * primary anti-enumeration / anti-spam control) layered with a looser
 * per-IP window (10/15min, catches a single host hammering many
 * addresses). Crossing EITHER returns 429 problem+json — the 429 is a
 * deliberate rate-limit signal, NOT an enumeration leak (it does not
 * reveal whether the email exists; both real and unknown emails hit the
 * same per-email bucket).
 */
final class PasswordResetRateLimitTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    private const string PROBE_EMAIL = 'aud030-probe@demo.localhost';

    protected function setUp(): void
    {
        parent::setUp();

        // Limiter state persists across tests (filesystem cache pool in
        // dev/test). Reset both buckets for the probe email + the
        // BrowserKit default IP so each test starts with a fresh budget.
        self::getContainer()->get('limiter.password_reset_email')->create(self::PROBE_EMAIL)->reset();
        self::getContainer()->get('limiter.password_reset_ip')->create('127.0.0.1')->reset();
    }

    #[Test]
    public function sixthResetRequestForSameEmailReturns429(): void
    {
        $client = static::createClient();

        // The per-email limiter allows 5 requests per 15-minute window.
        // No User row is seeded — the service returns null (account not
        // found) and the controller still answers 200 (anti-enumeration).
        // The limiter ticks regardless, so the 6th request is rejected.
        for ($i = 1; $i <= 5; ++$i) {
            $client->request('POST', '/api/auth/password-reset/request', [
                'headers' => ['content-type' => 'application/json'],
                'body' => json_encode(['email' => self::PROBE_EMAIL], JSON_THROW_ON_ERROR),
            ]);
            self::assertResponseStatusCodeSame(
                200,
                'Attempt #'.$i.' must pass (always-200 anti-enumeration).',
            );
        }

        $response = $client->request('POST', '/api/auth/password-reset/request', [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode(['email' => self::PROBE_EMAIL], JSON_THROW_ON_ERROR),
        ]);

        self::assertResponseStatusCodeSame(429);
        self::assertNotNull($response->getHeaders(throw: false)['retry-after'][0] ?? null);
    }
}
