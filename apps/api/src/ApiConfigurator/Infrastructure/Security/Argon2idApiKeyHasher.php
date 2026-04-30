<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Infrastructure\Security;

use App\ApiConfigurator\Domain\Service\ApiKeyHasherInterface;

use const PASSWORD_ARGON2ID;

/**
 * Argon2id digest backed by `password_hash(PASSWORD_ARGON2ID)`.
 *
 * Per ADR-0016 we lean on PHP's defaults rather than carrying a custom
 * cost table — defaults track the language-level recommendation as PHP
 * releases. `password_needs_rehash` does the rotation accounting on
 * the same parameters, so admins do not maintain a parallel knob.
 */
final class Argon2idApiKeyHasher implements ApiKeyHasherInterface
{
    public function hash(string $rawKey): string
    {
        return password_hash($rawKey, PASSWORD_ARGON2ID);
    }

    public function verify(string $rawKey, string $hash): bool
    {
        return password_verify($rawKey, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_ARGON2ID);
    }
}
