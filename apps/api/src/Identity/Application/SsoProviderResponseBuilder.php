<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Domain\Entity\SsoProvider;
use DateTimeInterface;

/**
 * RBAC-P5-014 (#704) — projection helper for the Settings → SSO config
 * tab.
 *
 * Secrets are masked on the way out: `client_secret`, `idp_certificate`,
 * and any field whose key contains `secret` or `private_key` is
 * replaced with `'****'` so the API never surfaces plaintext to the
 * admin UI. The write path accepts plaintext (until ByokKeyManager
 * lands per the SsoProvider docblock) — but reads stay safe.
 */
final class SsoProviderResponseBuilder
{
    private const array SECRET_KEYS = [
        'client_secret',
        'private_key',
        'idp_certificate',
        'sp_private_key',
    ];

    /**
     * @return array{
     *     id: string,
     *     kind: string,
     *     name: string,
     *     enabled: bool,
     *     config: array<string, mixed>,
     *     created_at: string,
     *     updated_at: ?string
     * }
     */
    public function buildOne(SsoProvider $provider): array
    {
        return [
            'id' => $provider->getId()->toRfc4122(),
            'kind' => $provider->getKind(),
            'name' => $provider->getName(),
            'enabled' => $provider->isEnabled(),
            'config' => self::maskConfig($provider->getConfig()),
            'created_at' => $provider->getCreatedAt()->format(DateTimeInterface::ATOM),
            'updated_at' => $provider->getUpdatedAt()?->format(DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param iterable<SsoProvider> $providers
     *
     * @return list<array{id: string, kind: string, name: string, enabled: bool, config: array<string, mixed>, created_at: string, updated_at: ?string}>
     */
    public function buildList(iterable $providers): array
    {
        $out = [];
        foreach ($providers as $provider) {
            $out[] = $this->buildOne($provider);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    private static function maskConfig(array $config): array
    {
        $masked = [];
        foreach ($config as $key => $value) {
            $lowerKey = strtolower($key);
            $isSecret = false;
            foreach (self::SECRET_KEYS as $secretKey) {
                if ($lowerKey === $secretKey || str_contains($lowerKey, $secretKey)) {
                    $isSecret = true;
                    break;
                }
            }
            if ($isSecret && \is_string($value) && '' !== $value) {
                $masked[$key] = '****';
                continue;
            }
            $masked[$key] = $value;
        }

        return $masked;
    }
}
