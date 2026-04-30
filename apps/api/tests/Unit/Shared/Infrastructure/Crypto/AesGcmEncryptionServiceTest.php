<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Infrastructure\Crypto;

use App\Shared\Application\Crypto\EncryptedSecret;
use App\Shared\Infrastructure\Crypto\AesGcmEncryptionService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Coverage for the BYOK at-rest encryption surface (#107 / 0.11.12).
 *
 * Skips itself on hosts without AES-NI hardware (libsodium AES-256-GCM
 * `is_available()` returns false on emulated ARM and on some CI
 * runners). The runtime check in `encrypt()` covers production safety;
 * the test gives operators a fast smoke when running locally on the
 * supported hardware.
 */
final class AesGcmEncryptionServiceTest extends TestCase
{
    private const string KEY_V1 = 'AAECAwQFBgcICQoLDA0ODxAREhMUFRYXGBkaGxwdHh8=';
    private const string KEY_V2 = 'ifn4K04hpi/Gq5KqHYBlstuaxuofrlJmQw4wPYdWTTY=';

    protected function setUp(): void
    {
        if (!\function_exists('sodium_crypto_aead_aes256gcm_is_available')
            || !sodium_crypto_aead_aes256gcm_is_available()) {
            self::markTestSkipped('AES-256-GCM not available (no AES-NI / libsodium build).');
        }
    }

    #[Test]
    public function rejectsEmptyKeyMap(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('requires at least one master key');
        new AesGcmEncryptionService([]);
    }

    #[Test]
    public function rejectsKeyOfWrongLength(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not a valid base64 32-byte string');
        new AesGcmEncryptionService([1 => base64_encode('too-short')]);
    }

    #[Test]
    public function encryptDecryptRoundTrip(): void
    {
        $service = new AesGcmEncryptionService([1 => self::KEY_V1]);
        $plaintext = 'sk-ant-api03-secret-12345';

        $secret = $service->encrypt($plaintext);

        self::assertSame(1, $secret->version);
        self::assertNotSame($plaintext, $secret->ciphertext);
        self::assertSame($plaintext, $service->decrypt($secret));
    }

    #[Test]
    public function tamperedCiphertextIsRejected(): void
    {
        $service = new AesGcmEncryptionService([1 => self::KEY_V1]);
        $secret = $service->encrypt('important-secret');

        $tampered = new EncryptedSecret(
            ciphertext: substr_replace($secret->ciphertext, 'X', -3, 1),
            version: 1,
        );

        $this->expectException(RuntimeException::class);
        // Either malformed (base64 fails) or auth-tag mismatch (GCM
        // rejects). Both surface as the same hardened message.
        $this->expectExceptionMessageMatches('/(integrity check|malformed)/');
        $service->decrypt($tampered);
    }

    #[Test]
    public function differentVersionDecodesAndRotatesLazily(): void
    {
        $serviceV1 = new AesGcmEncryptionService([1 => self::KEY_V1]);
        $secret = $serviceV1->encrypt('hello');

        // Operator adds V2; reading the V1 ciphertext still works,
        // and `needsRotation()` flags it for lazy re-encrypt.
        $serviceV2 = new AesGcmEncryptionService([
            1 => self::KEY_V1,
            2 => self::KEY_V2,
        ]);

        self::assertSame('hello', $serviceV2->decrypt($secret));
        self::assertTrue($serviceV2->needsRotation($secret));

        // Fresh writes go to V2.
        $rotated = $serviceV2->encrypt('hello');
        self::assertSame(2, $rotated->version);
        self::assertFalse($serviceV2->needsRotation($rotated));
    }

    #[Test]
    public function decryptRejectsUnknownVersion(): void
    {
        $service = new AesGcmEncryptionService([1 => self::KEY_V1]);
        $unknown = new EncryptedSecret(ciphertext: 'AAA=', version: 999);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not loaded');
        $service->decrypt($unknown);
    }
}
