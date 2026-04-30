<?php

declare(strict_types=1);

namespace App\Tests\Unit\ApiConfigurator;

use App\ApiConfigurator\Infrastructure\Security\Argon2idApiKeyHasher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class Argon2idApiKeyHasherTest extends TestCase
{
    #[Test]
    public function hashAndVerifyRoundTrip(): void
    {
        $hasher = new Argon2idApiKeyHasher();
        $raw = 'pim_live_abc123def456ghi789jkl012mno345pq';

        $hash = $hasher->hash($raw);

        self::assertNotSame($raw, $hash);
        self::assertStringStartsWith('$argon2id$', $hash);
        self::assertTrue($hasher->verify($raw, $hash));
    }

    #[Test]
    public function verifyRejectsMismatch(): void
    {
        $hasher = new Argon2idApiKeyHasher();
        $hash = $hasher->hash('correct-secret');

        self::assertFalse($hasher->verify('wrong-secret', $hash));
        self::assertFalse($hasher->verify('correct-secret-with-suffix', $hash));
    }

    #[Test]
    public function differentInputsProduceDifferentDigests(): void
    {
        $hasher = new Argon2idApiKeyHasher();

        $a = $hasher->hash('one');
        $b = $hasher->hash('two');

        self::assertNotSame($a, $b);
    }

    #[Test]
    public function sameInputProducesDifferentDigestsBecauseOfSalt(): void
    {
        $hasher = new Argon2idApiKeyHasher();

        $a = $hasher->hash('same');
        $b = $hasher->hash('same');

        // Argon2id embeds a random salt — the digests differ even for
        // the same input. Both still verify.
        self::assertNotSame($a, $b);
        self::assertTrue($hasher->verify('same', $a));
        self::assertTrue($hasher->verify('same', $b));
    }

    #[Test]
    public function freshDigestDoesNotNeedRehash(): void
    {
        $hasher = new Argon2idApiKeyHasher();
        $hash = $hasher->hash('any');

        self::assertFalse($hasher->needsRehash($hash));
    }
}
