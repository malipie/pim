<?php

declare(strict_types=1);

namespace App\Channel\Contracts;

use App\Shared\Domain\Tenant;

/**
 * Cross-BC contract for locale code normalization (#1228).
 *
 * Exposed from `Channel\Contracts` (not `Channel\Internals`) so consumers
 * in other bounded contexts — completeness per-locale (#1152), the channel
 * publication profile + export (T3.x) — can convert between the SHORT
 * (`pl`) and BCP-47 (`pl_PL`) shapes without reaching into Channel
 * internals (Deptrac: `*_Internals -> Channel_Contracts` is allowed).
 *
 * The SHORT direction is pure ({@see \App\Channel\Domain\LocaleCode}); the
 * reverse needs the tenant's enabled locales to disambiguate
 * (`de` -> `de_DE` | `de_AT`), hence a tenant-scoped resolver.
 */
interface LocaleCodeResolverInterface
{
    /**
     * Short (language-only) form of any locale code: `pl_PL` -> `pl`.
     */
    public function toShort(string $code): string;

    /**
     * BCP-47 form of a short code, resolved against the tenant's active
     * locales: `pl` -> `pl_PL`. Returns null when no active tenant locale
     * matches the language. When several active locales share the language
     * (`de_DE` + `de_AT`), the first by sort order wins.
     */
    public function toBcp47(string $short, Tenant $tenant): ?string;
}
