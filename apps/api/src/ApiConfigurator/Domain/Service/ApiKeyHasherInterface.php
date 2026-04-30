<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Domain\Service;

/**
 * Argon2id hashing contract for ApiKey raw secrets.
 *
 * The `pim:apikey:generate` command and the future
 * `ApiKeyAuthenticator` (#94) both go through this interface so the
 * concrete algorithm stays swappable — see ADR-0016 for the choice.
 */
interface ApiKeyHasherInterface
{
    /**
     * Argon2id-hash a raw API key. The output starts with `$argon2id$…`
     * and embeds the cost parameters used at issue time, which lets
     * {@see needsRehash()} flag stale entries on rotation.
     */
    public function hash(string $rawKey): string;

    /**
     * Constant-time compare a raw key against a stored digest.
     */
    public function verify(string $rawKey, string $hash): bool;

    /**
     * `true` when the digest was produced with cost parameters older
     * than the current PHP defaults — caller should re-hash on next
     * successful presentation.
     */
    public function needsRehash(string $hash): bool;
}
