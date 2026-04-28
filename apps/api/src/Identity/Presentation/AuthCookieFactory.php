<?php

declare(strict_types=1);

namespace App\Identity\Presentation;

use DateInterval;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Single source of truth for the refresh-token Set-Cookie header.
 *
 * The cookie is httpOnly + Secure + SameSite=Strict and pinned to
 * `/api/auth` so it never leaks to integration GETs against `/api/products`
 * or similar. The same factory builds the "clear" cookie used by logout —
 * keeping all attributes in one place stops them drifting apart.
 *
 * Configuration (cookie name, TTL, secure flag) is bound from `services.yaml`
 * so tests can shorten the TTL and the dev environment over plain HTTP can
 * disable `Secure` if the operator ever runs without Caddy TLS.
 */
final readonly class AuthCookieFactory
{
    public const string COOKIE_NAME_DEFAULT = 'pim_refresh_token';
    public const string COOKIE_PATH = '/api/auth';

    public function __construct(
        private string $cookieName = self::COOKIE_NAME_DEFAULT,
        private string $ttl = 'P30D',
        private bool $secure = true,
    ) {
    }

    public function getCookieName(): string
    {
        return $this->cookieName;
    }

    public function issue(string $rawToken, DateTimeImmutable $now): Cookie
    {
        $expires = $now->add(new DateInterval($this->ttl));

        return Cookie::create($this->cookieName)
            ->withValue($rawToken)
            ->withExpires($expires)
            ->withPath(self::COOKIE_PATH)
            ->withDomain(null)
            ->withSecure($this->secure)
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_STRICT);
    }

    public function clear(): Cookie
    {
        return Cookie::create($this->cookieName)
            ->withValue('')
            ->withExpires(1)
            ->withPath(self::COOKIE_PATH)
            ->withDomain(null)
            ->withSecure($this->secure)
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_STRICT);
    }
}
