<?php

declare(strict_types=1);

namespace App\Integration\Generic\Application;

use App\Integration\Generic\Domain\Entity\Connection;
use App\Shared\Application\Crypto\EncryptedSecret;
use App\Shared\Application\Crypto\EncryptionServiceInterface;

use const JSON_THROW_ON_ERROR;

/**
 * Reversible at-rest encryption for a {@see Connection}'s external-API
 * credentials (APIC-P1-02, ADR-0022 / ADR-0017).
 *
 * Producer-side keys are HASHED (Argon2id) because PIM only verifies an
 * incoming key; consumer-side credentials must be ENCRYPTED reversibly because
 * the sync runtime has to decrypt them and replay them against the remote API.
 * The two mechanisms deliberately coexist — see ADR-0022.
 *
 * The credential shape is a flat string→string map whose keys depend on the
 * connection's {@see \App\Integration\Generic\Domain\Enum\AuthType}
 * (e.g. `{header, value}` for api_key, `{user, pass}` for basic). The map is
 * JSON-encoded, encrypted via the BYOK service, and persisted as the
 * ciphertext + key-version columns. The GenericRestClient (APIC-P1-03) reads
 * it back through {@see reveal()}; the credentials never leave the server in an
 * API response (masking enforced where the resource is serialised, APIC-P1-06).
 */
final readonly class ConnectionCredentialsCipher
{
    public function __construct(
        private EncryptionServiceInterface $encryption,
    ) {
    }

    /**
     * Encrypts the credential map and stores ciphertext + key version on the
     * connection. An empty map clears any stored credentials (e.g. authType
     * switched to `none`).
     *
     * @param array<string, string> $credentials
     */
    public function apply(Connection $connection, array $credentials): void
    {
        if ([] === $credentials) {
            $connection->setCredentials(null, null);

            return;
        }

        $secret = $this->encryption->encrypt(json_encode($credentials, JSON_THROW_ON_ERROR));
        $connection->setCredentials($secret->ciphertext, $secret->version);
    }

    /**
     * Decrypts the stored credentials back into the credential map. Returns an
     * empty array when the connection has no stored credentials.
     *
     * @return array<string, string>
     */
    public function reveal(Connection $connection): array
    {
        $ciphertext = $connection->getCredentialsCiphertext();
        $version = $connection->getCredentialsKeyVersion();

        if (null === $ciphertext || null === $version) {
            return [];
        }

        $plaintext = $this->encryption->decrypt(new EncryptedSecret($ciphertext, $version));

        $decoded = json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);

        $credentials = [];
        if (\is_array($decoded)) {
            foreach ($decoded as $key => $value) {
                if (\is_string($key) && \is_string($value)) {
                    $credentials[$key] = $value;
                }
            }
        }

        return $credentials;
    }
}
