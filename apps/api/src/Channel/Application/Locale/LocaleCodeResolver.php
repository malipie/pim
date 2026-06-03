<?php

declare(strict_types=1);

namespace App\Channel\Application\Locale;

use App\Channel\Contracts\LocaleCodeResolverInterface;
use App\Channel\Domain\LocaleCode;
use App\Channel\Domain\Repository\TenantLocaleRepositoryInterface;
use App\Shared\Domain\Tenant;

/**
 * Locales feature (#1228) — resolves the tenant-ambiguous SHORT -> BCP-47
 * direction against a tenant's active locales.
 *
 * The SHORT direction delegates to the pure {@see LocaleCode}; the reverse
 * walks `findActiveForTenant` (ordered by sortOrder) and returns the first
 * active locale whose language matches. Matching is done on the catalog
 * code via {@see LocaleCode::toShort} rather than `Locale::$language`,
 * which is unpopulated for older seed rows.
 *
 * Per-request memoised through `$cache` keyed by `tenantId|short`; nulls
 * are cached too (a missing match is a stable answer for the request).
 */
final class LocaleCodeResolver implements LocaleCodeResolverInterface
{
    /**
     * @var array<string, string|null>
     */
    private array $cache = [];

    public function __construct(
        private readonly TenantLocaleRepositoryInterface $tenantLocales,
    ) {
    }

    public function toShort(string $code): string
    {
        return LocaleCode::toShort($code);
    }

    public function toBcp47(string $short, Tenant $tenant): ?string
    {
        $needle = LocaleCode::toShort($short);
        $cacheKey = $tenant->getId()->toRfc4122().'|'.$needle;
        if (\array_key_exists($cacheKey, $this->cache)) {
            return $this->cache[$cacheKey];
        }

        $resolved = null;
        foreach ($this->tenantLocales->findActiveForTenant($tenant) as $tenantLocale) {
            $code = $tenantLocale->getLocale()->getCode();
            if (LocaleCode::toShort($code) === $needle) {
                $resolved = $code;
                break;
            }
        }

        $this->cache[$cacheKey] = $resolved;

        return $resolved;
    }
}
