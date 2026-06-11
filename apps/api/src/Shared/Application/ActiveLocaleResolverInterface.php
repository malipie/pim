<?php

declare(strict_types=1);

namespace App\Shared\Application;

use App\Shared\Domain\Tenant;

/**
 * Cross-BC contract for the tenant's ACTIVE UI locale strip (#1352).
 *
 * The LOC-07 `tenant_locales` lifecycle (Settings → Languages:
 * soft-deactivate / reactivate / purge, owned by the Channel BC) is the
 * single source of truth for which locales i18n forms and locale pickers
 * may show. The contract lives in Shared — next to `Tenant` itself — so
 * Identity (workspace strip) and Catalog (detail picker) can consume it
 * without depending on Channel internals; Channel provides the
 * implementation.
 */
interface ActiveLocaleResolverInterface
{
    /**
     * Unique short language codes of the tenant's ACTIVE locales, default
     * first then by sortOrder (e.g. `['pl', 'en', 'de']`). Empty when the
     * tenant has no tenant_locales rows — caller applies its legacy
     * fallback.
     *
     * @return list<string>
     */
    public function languages(Tenant $tenant): array;

    /**
     * Short language code of the tenant's default locale, or null when no
     * tenant_locales rows exist (caller falls back to
     * `Tenant::getPrimaryLocale()`).
     */
    public function primaryLanguage(Tenant $tenant): ?string;
}
