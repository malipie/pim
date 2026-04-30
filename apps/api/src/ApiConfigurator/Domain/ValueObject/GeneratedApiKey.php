<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Domain\ValueObject;

/**
 * Output of {@see \App\ApiConfigurator\Application\ApiKeyGenerator::generate()}
 * — the raw secret that ships to the integrator (visible exactly once),
 * paired with the prefix and Argon2id hash that get persisted.
 *
 * `rawKey` MUST never reach the database. The `pim:apikey:generate`
 * command echoes it on stdout and the admin UI surfaces it through a
 * single modal post-create — both call sites discard it after display.
 */
final readonly class GeneratedApiKey
{
    public function __construct(
        public string $rawKey,
        public string $keyPrefix,
        public string $keyHash,
    ) {
    }
}
