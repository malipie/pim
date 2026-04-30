<?php

declare(strict_types=1);

namespace App\Tests\Unit\ApiConfigurator;

use App\ApiConfigurator\Domain\Entity\ApiKey;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApiKeyTest extends TestCase
{
    #[Test]
    public function constructorSetsFields(): void
    {
        $key = new ApiKey(
            keyHash: '$argon2id$v=19$m=65536,t=4,p=1$xxx',
            keyPrefix: 'pim_live_a4f2',
            name: 'Storefront key',
            scopes: ['storefront', 'sitemap'],
            rateLimitPerHour: 500,
        );

        self::assertSame('$argon2id$v=19$m=65536,t=4,p=1$xxx', $key->getKeyHash());
        self::assertSame('pim_live_a4f2', $key->getKeyPrefix());
        self::assertSame('Storefront key', $key->getName());
        self::assertSame(['storefront', 'sitemap'], $key->getScopes());
        self::assertSame(500, $key->getRateLimitPerHour());
        self::assertNull($key->getExpiresAt());
        self::assertNull($key->getRevokedAt());
        self::assertNull($key->getLastUsedAt());
        self::assertFalse($key->isRevoked());
    }

    #[Test]
    public function hasScopeMatchesExactly(): void
    {
        $key = $this->makeKey(['storefront', 'sitemap']);

        self::assertTrue($key->hasScope('storefront'));
        self::assertTrue($key->hasScope('sitemap'));
        self::assertFalse($key->hasScope('admin'));
        self::assertFalse($key->hasScope('Storefront'));
    }

    #[Test]
    public function revokeIsIdempotent(): void
    {
        $key = $this->makeKey();
        $first = new DateTimeImmutable('2026-04-30 10:00:00');
        $second = new DateTimeImmutable('2026-04-30 11:00:00');

        $key->revoke($first);
        $key->revoke($second);

        self::assertTrue($key->isRevoked());
        self::assertEquals($first, $key->getRevokedAt());
    }

    #[Test]
    public function isUsableTracksRevocationAndExpiry(): void
    {
        $now = new DateTimeImmutable('2026-04-30 12:00:00');
        $past = new DateTimeImmutable('2026-04-29 12:00:00');
        $future = new DateTimeImmutable('2026-05-30 12:00:00');

        $usable = new ApiKey('h', 'pim_live_aaaa', 'k', expiresAt: $future);
        self::assertTrue($usable->isUsable($now));

        $expired = new ApiKey('h', 'pim_live_bbbb', 'k', expiresAt: $past);
        self::assertFalse($expired->isUsable($now));

        $revoked = new ApiKey('h', 'pim_live_cccc', 'k');
        $revoked->revoke($past);
        self::assertFalse($revoked->isUsable($now));
    }

    #[Test]
    public function rehashReplacesDigest(): void
    {
        $key = $this->makeKey();
        $key->rehash('$argon2id$v=19$m=131072,t=4,p=1$new');

        self::assertSame('$argon2id$v=19$m=131072,t=4,p=1$new', $key->getKeyHash());
    }

    #[Test]
    public function markUsedRecordsTimestamp(): void
    {
        $key = $this->makeKey();
        $when = new DateTimeImmutable('2026-04-30 12:34:56');

        $key->markUsed($when);

        self::assertEquals($when, $key->getLastUsedAt());
    }

    /**
     * @param list<string> $scopes
     */
    private function makeKey(array $scopes = []): ApiKey
    {
        return new ApiKey(
            keyHash: 'hashed',
            keyPrefix: 'pim_live_a4f2',
            name: 'demo',
            scopes: $scopes,
        );
    }
}
