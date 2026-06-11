<?php

declare(strict_types=1);

namespace App\Channel\Application\Service;

use App\Channel\Domain\Entity\TenantLocale;
use App\Channel\Domain\LocaleCode;
use App\Channel\Domain\Repository\TenantLocaleRepositoryInterface;
use App\Shared\Application\ActiveLocaleResolverInterface;
use App\Shared\Domain\Tenant;

/**
 * #1352 (reopen #2) — single source of truth for the tenant's UI locale
 * strip. Two locale stores used to drift apart: the LOC-07
 * `tenant_locales` lifecycle (Settings → Languages, soft-deactivate /
 * reactivate / purge) and the legacy `Tenant.enabledLocales` JSONB
 * (written by the retired "+ Dodaj język" dialog, never cleaned on
 * deactivation). A locale added through the old dialog and then removed
 * in Settings haunted every i18n form as a ghost tab (operator's "IT").
 *
 * Every read surface (workspace strip, detail-page locale picker) now
 * derives short language codes from ACTIVE `tenant_locales` rows.
 * Tenants with no rows at all (legacy/dev fixtures) fall back to the
 * JSONB list so nothing regresses before LOC-07 seeding.
 */
final readonly class ActiveLocaleResolver implements ActiveLocaleResolverInterface
{
    public function __construct(
        private TenantLocaleRepositoryInterface $tenantLocales,
    ) {
    }

    /**
     * Unique short language codes of the tenant's ACTIVE locales, default
     * first then by sortOrder (e.g. `['pl', 'en', 'de']`). Empty when the
     * tenant has no tenant_locales rows — caller applies its legacy
     * fallback.
     *
     * @return list<string>
     */
    public function languages(Tenant $tenant): array
    {
        $rows = $this->tenantLocales->findActiveForTenant($tenant);
        usort($rows, static function ($a, $b): int {
            $byDefault = ($b->isDefault() ? 1 : 0) <=> ($a->isDefault() ? 1 : 0);

            return 0 !== $byDefault ? $byDefault : $a->getSortOrder() <=> $b->getSortOrder();
        });

        $languages = [];
        foreach ($rows as $row) {
            $language = self::shortLanguage($row);
            if ('' !== $language && !\in_array($language, $languages, true)) {
                $languages[] = $language;
            }
        }

        return $languages;
    }

    /**
     * Short language code of the tenant's default locale, or null when no
     * tenant_locales rows exist (caller falls back to
     * `Tenant::getPrimaryLocale()`).
     */
    public function primaryLanguage(Tenant $tenant): ?string
    {
        $default = $this->tenantLocales->findDefaultForTenant($tenant);
        if (null === $default) {
            return null;
        }
        $language = self::shortLanguage($default);

        return '' !== $language ? $language : null;
    }

    /**
     * Legacy catalog rows may carry an empty `language` column — fall
     * back to the BCP-47 code's language part (`pl_PL` -> `pl`).
     */
    private static function shortLanguage(TenantLocale $row): string
    {
        $language = $row->getLocale()->getLanguage();

        return '' !== $language ? $language : LocaleCode::toShort($row->getLocale()->getCode());
    }
}
