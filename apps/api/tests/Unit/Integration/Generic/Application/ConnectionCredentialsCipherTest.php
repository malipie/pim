<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Generic\Application;

use App\Integration\Generic\Application\ConnectionCredentialsCipher;
use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Enum\AuthType;
use App\Shared\Application\Crypto\EncryptedSecret;
use App\Shared\Application\Crypto\EncryptionServiceInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Covers the cipher's own logic (map ↔ JSON ↔ stored columns, clear-on-empty,
 * round-trip) with a deterministic fake encryption service. The AES-256-GCM
 * primitive itself is covered by AesGcmEncryptionServiceTest (which skips on
 * hosts without AES-NI); this suite must run everywhere, so it does not touch
 * libsodium.
 */
final class ConnectionCredentialsCipherTest extends TestCase
{
    #[Test]
    public function applyEncryptsAndStoresCiphertextWithVersion(): void
    {
        $cipher = new ConnectionCredentialsCipher($this->fakeEncryption());
        $connection = $this->connection();

        $cipher->apply($connection, ['header' => 'X-Api-Key', 'value' => 's3cr3t']);

        self::assertSame(7, $connection->getCredentialsKeyVersion());
        $ciphertext = $connection->getCredentialsCiphertext();
        self::assertNotNull($ciphertext);
        // The stored blob is not the raw secret value.
        self::assertStringNotContainsString('s3cr3t', $ciphertext);
    }

    #[Test]
    public function applyWithEmptyMapClearsStoredCredentials(): void
    {
        $cipher = new ConnectionCredentialsCipher($this->fakeEncryption());
        $connection = $this->connection();
        $cipher->apply($connection, ['token' => 'abc']);
        self::assertNotNull($connection->getCredentialsCiphertext());

        $cipher->apply($connection, []);

        self::assertNull($connection->getCredentialsCiphertext());
        self::assertNull($connection->getCredentialsKeyVersion());
    }

    #[Test]
    public function revealRoundTripsTheCredentialMap(): void
    {
        $cipher = new ConnectionCredentialsCipher($this->fakeEncryption());
        $connection = $this->connection();
        $credentials = ['user' => 'alice', 'pass' => 'hunter2'];

        $cipher->apply($connection, $credentials);

        self::assertSame($credentials, $cipher->reveal($connection));
    }

    #[Test]
    public function revealReturnsEmptyArrayWhenNoCredentialsStored(): void
    {
        $cipher = new ConnectionCredentialsCipher($this->fakeEncryption());

        self::assertSame([], $cipher->reveal($this->connection()));
    }

    #[Test]
    public function rotateIfNeededReEncryptsStaleCredentials(): void
    {
        $credentials = ['header' => 'X-Api-Key', 'value' => 's3cr3t'];

        // Sealed under key v1.
        $connection = $this->connection();
        new ConnectionCredentialsCipher($this->versionedEncryption(1))->apply($connection, $credentials);
        self::assertSame(1, $connection->getCredentialsKeyVersion());

        // Active key is now v2 — the stale blob must rotate up.
        $cipher = new ConnectionCredentialsCipher($this->versionedEncryption(2));
        self::assertTrue($cipher->needsRotation($connection));
        self::assertTrue($cipher->rotateIfNeeded($connection));

        self::assertSame(2, $connection->getCredentialsKeyVersion());
        self::assertSame($credentials, $cipher->reveal($connection), 'credentials survive rotation');
        // Idempotent: a second sweep is a no-op once on the active version.
        self::assertFalse($cipher->needsRotation($connection));
        self::assertFalse($cipher->rotateIfNeeded($connection));
    }

    #[Test]
    public function rotateIfNeededIsNoopWhenAlreadyOnActiveVersion(): void
    {
        $cipher = new ConnectionCredentialsCipher($this->versionedEncryption(2));
        $connection = $this->connection();
        $cipher->apply($connection, ['token' => 'abc']);
        $before = $connection->getCredentialsCiphertext();

        self::assertFalse($cipher->rotateIfNeeded($connection));
        self::assertSame($before, $connection->getCredentialsCiphertext());
    }

    #[Test]
    public function rotateIfNeededIsNoopWhenNoCredentialsStored(): void
    {
        $cipher = new ConnectionCredentialsCipher($this->versionedEncryption(2));

        self::assertFalse($cipher->needsRotation($this->connection()));
        self::assertFalse($cipher->rotateIfNeeded($this->connection()));
    }

    private function connection(): Connection
    {
        return new Connection('idosell', 'IdoSell PL', 'https://api.idosell.com', AuthType::ApiKey);
    }

    /**
     * Fake whose active version is configurable and which flags any blob older
     * than that as needing rotation — mirrors AesGcmEncryptionService's versioned
     * contract without touching libsodium.
     */
    private function versionedEncryption(int $activeVersion): EncryptionServiceInterface
    {
        return new class($activeVersion) implements EncryptionServiceInterface {
            public function __construct(private readonly int $activeVersion)
            {
            }

            public function encrypt(string $plaintext): EncryptedSecret
            {
                return new EncryptedSecret(base64_encode($plaintext), $this->activeVersion);
            }

            public function decrypt(EncryptedSecret $secret): string
            {
                $decoded = base64_decode($secret->ciphertext, true);

                return false === $decoded ? '' : $decoded;
            }

            public function needsRotation(EncryptedSecret $secret): bool
            {
                return $secret->version < $this->activeVersion;
            }
        };
    }

    private function fakeEncryption(): EncryptionServiceInterface
    {
        return new class implements EncryptionServiceInterface {
            public function encrypt(string $plaintext): EncryptedSecret
            {
                return new EncryptedSecret(base64_encode($plaintext), 7);
            }

            public function decrypt(EncryptedSecret $secret): string
            {
                $decoded = base64_decode($secret->ciphertext, true);

                return false === $decoded ? '' : $decoded;
            }

            public function needsRotation(EncryptedSecret $secret): bool
            {
                return false;
            }
        };
    }
}
