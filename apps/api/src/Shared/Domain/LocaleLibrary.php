<?php

declare(strict_types=1);

namespace App\Shared\Domain;

/**
 * Single source of truth for the set of locales the platform recognizes.
 *
 * Used by:
 *   - `Tenant::enableLocale()` to validate input from the workspace API
 *     (`POST /api/workspaces/current/locales`),
 *   - `WorkspaceController` for 400 RFC 7807 mapping when an unknown code
 *     reaches the endpoint,
 *   - the FE `LOCALE_LIBRARY` constant in `apps/admin/src/lib/locales.ts`
 *     (kept in sync via a smoke test that imports both lists).
 *
 * Adding a locale here is a deliberate platform decision — every entry
 * carries downstream cost (translation completeness ratios, JSONB column
 * sizes, fallback chains in the FE LocaleTabsField). Don't expand this
 * silently; require an ADR / product approval first.
 */
final class LocaleLibrary
{
    /**
     * @var list<string>
     */
    public const array CODES = [
        'pl', 'en', 'de', 'fr', 'it', 'es', 'pt', 'nl',
        'cs', 'sk', 'ru', 'uk', 'hu', 'ro',
    ];

    public static function isSupported(string $locale): bool
    {
        return \in_array($locale, self::CODES, true);
    }
}
