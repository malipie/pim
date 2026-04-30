<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Application;

use App\ApiConfigurator\Domain\Service\ApiKeyHasherInterface;
use App\ApiConfigurator\Domain\ValueObject\GeneratedApiKey;

/**
 * Generates the raw API key, the display prefix, and the Argon2id hash
 * in a single pass. See ADR-0016 for the format specification.
 *
 * The generator runs once at issue time — the `pim:apikey:generate`
 * command and the future admin "Generate" button are the only
 * call sites. The raw key is returned to the caller and never
 * persisted; callers MUST display it once and discard.
 */
final class ApiKeyGenerator
{
    private const string PREFIX_NAMESPACE = 'pim';
    private const int RAW_BODY_BYTES = 32;
    private const int PREFIX_LENGTH = 12;

    /**
     * Base62 alphabet — digits + uppercase + lowercase. URL- and
     * shell-safe without quoting; deterministic length per byte
     * count when re-encoded from binary.
     */
    private const string BASE62_ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    public function __construct(
        private readonly ApiKeyHasherInterface $hasher,
        private readonly string $environment,
    ) {
    }

    public function generate(): GeneratedApiKey
    {
        $envSegment = $this->environmentSegment();
        $body = $this->randomBase62();
        $rawKey = \sprintf('%s_%s_%s', self::PREFIX_NAMESPACE, $envSegment, $body);
        $prefix = substr($rawKey, 0, self::PREFIX_LENGTH);
        $hash = $this->hasher->hash($rawKey);

        return new GeneratedApiKey($rawKey, $prefix, $hash);
    }

    /**
     * Maps `APP_ENV` to the four-letter forensic segment from ADR-0016.
     * `prod` collapses to `live` so the printed key matches what
     * production callers expect (`pim_live_…`).
     */
    private function environmentSegment(): string
    {
        return match ($this->environment) {
            'prod' => 'live',
            'dev' => 'dev',
            'test' => 'test',
            default => 'live',
        };
    }

    private function randomBase62(): string
    {
        $bytes = random_bytes(self::RAW_BODY_BYTES);
        $alphabet = self::BASE62_ALPHABET;
        $alphabetLength = \strlen($alphabet);
        $out = '';

        for ($i = 0, $len = \strlen($bytes); $i < $len; ++$i) {
            $out .= $alphabet[\ord($bytes[$i]) % $alphabetLength];
        }

        return $out;
    }
}
