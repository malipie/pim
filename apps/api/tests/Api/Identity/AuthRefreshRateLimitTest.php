<?php

declare(strict_types=1);

namespace App\Tests\Api\Identity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Coverage for the refresh-token rate limiter (#97 / 0.11.2).
 *
 * 30 attempts per IP per hour — the listener returns 429 with
 * `Retry-After` on the 31st call, regardless of whether the cookie
 * is valid (rotation attempts and replay attempts compete for the
 * same budget).
 */
final class AuthRefreshRateLimitTest extends ApiTestCase
{
    protected static ?bool $alwaysBootKernel = true;

    protected function setUp(): void
    {
        parent::setUp();
        // Reset the bucket for the BrowserKit default IP between tests.
        self::getContainer()->get('limiter.auth_refresh')->create('127.0.0.1')->reset();
    }

    #[Test]
    public function thirtyFirstRefreshAttemptInWindowReturns429(): void
    {
        $client = static::createClient();

        // 30 cookie-less attempts — Lexik returns 401 for each (no token
        // payload to refresh). The limiter ticks anyway, so the 31st
        // call gets 429 from the listener before Lexik even runs.
        for ($i = 1; $i <= 30; ++$i) {
            $r = $client->request('POST', '/api/auth/refresh');
            self::assertNotSame(429, $r->getStatusCode(), 'Attempt #'.$i.' must not be rate-limited yet.');
        }

        $response = $client->request('POST', '/api/auth/refresh');
        self::assertResponseStatusCodeSame(429);
        self::assertNotNull($response->getHeaders(throw: false)['retry-after'][0] ?? null);
    }
}
