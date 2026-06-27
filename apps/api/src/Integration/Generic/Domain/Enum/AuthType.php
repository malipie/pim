<?php

declare(strict_types=1);

namespace App\Integration\Generic\Domain\Enum;

/**
 * Authentication strategy a {@see \App\Integration\Generic\Domain\Entity\Connection}
 * uses against the external API (ADR-0022, epic APIC).
 *
 * MVP scope covers static credential schemes only; full OAuth2
 * authorization-code is a deferred §7 hook (APIC-P3-16).
 */
enum AuthType: string
{
    case None = 'none';
    case ApiKey = 'api_key';
    case Bearer = 'bearer';
    case Basic = 'basic';
    case Oauth2Token = 'oauth2_token';

    /**
     * Whether this scheme needs stored (reversibly-encrypted) credentials.
     * `none` is the only credential-free scheme.
     */
    public function requiresCredentials(): bool
    {
        return self::None !== $this;
    }
}
