<?php

declare(strict_types=1);

namespace App\Shared\Application\Crypto;

/**
 * Wire format for an at-rest secret: opaque base64 ciphertext + the
 * key version that produced it. Stored as two columns rather than a
 * single concatenated blob so audit-log diffs stay readable and a
 * future Vault swap can replace the version field with a Vault key id
 * without touching the ciphertext column.
 */
final readonly class EncryptedSecret
{
    public function __construct(
        /**
         * Base64 encoding of `nonce || ciphertext || authTag` — opaque
         * to callers, decoded inside the encryption service only.
         */
        public string $ciphertext,
        /**
         * Master key version this blob was produced with. Numeric so
         * monotonic rotation tooling stays trivial; the application
         * loads `APP_BYOK_KEY_V<n>` per version up to the current.
         */
        public int $version,
    ) {
    }
}
