<?php

declare(strict_types=1);

namespace App\Channel\Domain;

/**
 * Locale code normalization (#1228).
 *
 * The codebase carries locales in two shapes and silently mixing them
 * mis-keys per-locale rows:
 *   - SHORT (ISO 639-1, e.g. `pl`) — {@see \App\Shared\Domain\Tenant::$primaryLocale},
 *     `Tenant::$enabledLocales`, `ObjectValue::$locale`, the `?locale=`
 *     query param, and the `per_locale` completeness keys.
 *   - BCP-47 (e.g. `pl_PL`) — the global {@see Entity\Locale} catalog and
 *     `Channel::$locales`.
 *
 * This value helper owns the SHORT direction (the high-frequency one:
 * turning a full catalog / Channel code into the short code used
 * everywhere else). The tenant-ambiguous reverse (`de` -> `de_DE` | `de_AT`)
 * lives in {@see \App\Channel\Contracts\LocaleCodeResolverInterface}, which
 * resolves against the tenant's enabled locales.
 *
 * Pure + stateless so it is safe to call from hot paths (the read overlay,
 * completeness rebuild) without a service round-trip.
 */
final class LocaleCode
{
    /**
     * Short (language-only) form of a locale code, lower-cased.
     *
     * `pl_PL` -> `pl`, `en-US` -> `en`, `pl` -> `pl`, `PL` -> `pl`.
     * Accepts either `_` or `-` as the region separator.
     */
    public static function toShort(string $code): string
    {
        $separator = self::separatorPos($code);
        $language = null === $separator ? $code : substr($code, 0, $separator);

        return strtolower($language);
    }

    /**
     * Region subtag (upper-cased) or null when the code carries none.
     *
     * `pl_PL` -> `PL`, `en-us` -> `US`, `pl` -> null, `pl_` -> null.
     */
    public static function region(string $code): ?string
    {
        $separator = self::separatorPos($code);
        if (null === $separator) {
            return null;
        }
        $region = substr($code, $separator + 1);

        return '' === $region ? null : strtoupper($region);
    }

    /**
     * Whether the code carries a region subtag (`pl_PL` true, `pl` false).
     */
    public static function hasRegion(string $code): bool
    {
        return null !== self::region($code);
    }

    /**
     * Position of the first `_` / `-` region separator, or null when the
     * code is language-only.
     */
    private static function separatorPos(string $code): ?int
    {
        $underscore = strpos($code, '_');
        $hyphen = strpos($code, '-');

        if (false === $underscore && false === $hyphen) {
            return null;
        }
        if (false === $underscore) {
            return $hyphen;
        }
        if (false === $hyphen) {
            return $underscore;
        }

        return min($underscore, $hyphen);
    }
}
