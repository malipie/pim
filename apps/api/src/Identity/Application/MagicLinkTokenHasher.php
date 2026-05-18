<?php

declare(strict_types=1);

namespace App\Identity\Application;

/**
 * SHA-256 hash + cryptographic-random plaintext generator dla magic-link
 * tokens (Invitation, PasswordResetToken). Same algorithm as the
 * RbacApiTokenAuthenticator hash for cross-service consistency.
 *
 * `generate()` produces 64-hex-char (256-bit entropy) tokens.
 */
final class MagicLinkTokenHasher
{
    public function generate(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function hash(string $plaintext): string
    {
        return hash('sha256', $plaintext);
    }
}
