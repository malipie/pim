<?php

declare(strict_types=1);

namespace App\Shared\Application\Crypto;

/**
 * AEAD-style encryption surface for at-rest secrets (#107 / 0.11.12).
 *
 * Returns base64-encoded `(version || ciphertext-with-tag)` blobs so
 * a Doctrine TEXT column suffices and a future Vault swap can reuse
 * the same column shape — the version field carries cipher + key
 * generation in one opaque token (per ADR-0017).
 *
 * `encrypt()` always uses the configured "active" version. `decrypt()`
 * accepts any version still loaded into the runtime — older versions
 * stay decryptable until operators sweep them.
 */
interface EncryptionServiceInterface
{
    public function encrypt(string $plaintext): EncryptedSecret;

    public function decrypt(EncryptedSecret $secret): string;

    /**
     * `true` when the secret was encrypted with a version older than
     * the current active one. Callers can lazy-reencrypt on the next
     * write — same pattern Argon2id uses for password rehash.
     */
    public function needsRotation(EncryptedSecret $secret): bool;
}
