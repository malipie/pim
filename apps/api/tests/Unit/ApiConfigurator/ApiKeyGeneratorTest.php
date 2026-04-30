<?php

declare(strict_types=1);

namespace App\Tests\Unit\ApiConfigurator;

use App\ApiConfigurator\Application\ApiKeyGenerator;
use App\ApiConfigurator\Infrastructure\Security\Argon2idApiKeyHasher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApiKeyGeneratorTest extends TestCase
{
    #[Test]
    public function generateProducesPimEnvFormatRawKey(): void
    {
        $generator = new ApiKeyGenerator(new Argon2idApiKeyHasher(), 'prod');

        $generated = $generator->generate();

        self::assertMatchesRegularExpression('/^pim_live_[A-Za-z0-9]{32}$/', $generated->rawKey);
    }

    #[Test]
    public function devEnvironmentSegmentSurfacesInRawKey(): void
    {
        $generator = new ApiKeyGenerator(new Argon2idApiKeyHasher(), 'dev');

        $generated = $generator->generate();

        self::assertStringStartsWith('pim_dev_', $generated->rawKey);
    }

    #[Test]
    public function testEnvironmentSegmentSurfacesInRawKey(): void
    {
        $generator = new ApiKeyGenerator(new Argon2idApiKeyHasher(), 'test');

        $generated = $generator->generate();

        self::assertStringStartsWith('pim_test_', $generated->rawKey);
    }

    #[Test]
    public function unknownEnvironmentFallsBackToLive(): void
    {
        $generator = new ApiKeyGenerator(new Argon2idApiKeyHasher(), 'staging');

        $generated = $generator->generate();

        self::assertStringStartsWith('pim_live_', $generated->rawKey);
    }

    #[Test]
    public function prefixIsFirst12CharsOfRawKey(): void
    {
        $generator = new ApiKeyGenerator(new Argon2idApiKeyHasher(), 'prod');

        $generated = $generator->generate();

        self::assertSame(substr($generated->rawKey, 0, 12), $generated->keyPrefix);
        self::assertSame(12, \strlen($generated->keyPrefix));
    }

    #[Test]
    public function persistedHashVerifiesAgainstRawKey(): void
    {
        $hasher = new Argon2idApiKeyHasher();
        $generator = new ApiKeyGenerator($hasher, 'prod');

        $generated = $generator->generate();

        self::assertTrue($hasher->verify($generated->rawKey, $generated->keyHash));
        self::assertFalse($hasher->verify('pim_live_wrong', $generated->keyHash));
    }

    #[Test]
    public function generatedKeysAreUnique(): void
    {
        $generator = new ApiKeyGenerator(new Argon2idApiKeyHasher(), 'prod');

        $first = $generator->generate();
        $second = $generator->generate();

        self::assertNotSame($first->rawKey, $second->rawKey);
        self::assertNotSame($first->keyHash, $second->keyHash);
    }
}
