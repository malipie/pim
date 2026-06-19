<?php

declare(strict_types=1);

namespace App\Tests\Api\Identity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;

use const JSON_THROW_ON_ERROR;

/**
 * Coverage for AUD-030 (W2-12) — rate limiter on
 * `POST /api/invitations/{token}/accept`.
 *
 * The accept endpoint is PUBLIC_ACCESS (the magic-link token IS the auth
 * factor) and was unbounded before AUD-030: an attacker could brute the
 * 64-hex token space or hammer the password-hashing side-effect. A
 * per-IP limiter (10/15min) now caps the attempt rate; the 11th request
 * from one IP returns 429 before the controller even validates the
 * token.
 *
 * The token used here is a syntactically valid 64-hex string that does
 * not match any invitation — the controller answers 400 (bad request)
 * for the first 10 attempts, but the limiter ticks first, so the 11th
 * is a 429.
 */
final class InvitationAcceptRateLimitTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    /**
     * Syntactically valid (matches the `[a-f0-9]{64}` {token} route
     * requirement) but unmapped. Deliberately low-entropy (repeated
     * `deadbeef`) so the secret-scanner does not flag a test fixture as a
     * leaked credential.
     */
    private const string UNKNOWN_TOKEN = 'deadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeefdeadbeef';

    protected function setUp(): void
    {
        parent::setUp();

        // Reset the per-IP bucket for the BrowserKit default IP between tests.
        self::getContainer()->get('limiter.invitation_accept')->create('127.0.0.1')->reset();
    }

    #[Test]
    public function eleventhAcceptAttemptFromSameIpReturns429(): void
    {
        $client = static::createClient();

        // 10 attempts with a valid password but an unknown token — each
        // hits the controller and returns 400 (LogicException → bad
        // request). The limiter ticks on every call regardless.
        for ($i = 1; $i <= 10; ++$i) {
            $r = $client->request('POST', '/api/invitations/'.self::UNKNOWN_TOKEN.'/accept', [
                'headers' => ['content-type' => 'application/json'],
                'body' => json_encode(['password' => 'sufficiently-long-pass'], JSON_THROW_ON_ERROR),
            ]);
            self::assertNotSame(429, $r->getStatusCode(), 'Attempt #'.$i.' must not be rate-limited yet.');
        }

        $response = $client->request('POST', '/api/invitations/'.self::UNKNOWN_TOKEN.'/accept', [
            'headers' => ['content-type' => 'application/json'],
            'body' => json_encode(['password' => 'sufficiently-long-pass'], JSON_THROW_ON_ERROR),
        ]);

        self::assertResponseStatusCodeSame(429);
        self::assertNotNull($response->getHeaders(throw: false)['retry-after'][0] ?? null);
    }
}
