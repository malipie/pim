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

    private function connection(): Connection
    {
        return new Connection('idosell', 'IdoSell PL', 'https://api.idosell.com', AuthType::ApiKey);
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
