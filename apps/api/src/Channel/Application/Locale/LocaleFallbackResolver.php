<?php

declare(strict_types=1);

namespace App\Channel\Application\Locale;

use App\Channel\Contracts\LocaleFallbackResolverInterface;
use App\Channel\Domain\Entity\TenantLocale;
use App\Channel\Domain\Repository\TenantLocaleRepositoryInterface;
use App\Shared\Domain\Tenant;

/**
 * Locales feature (#872, LOC-04) — resolves a locale's fallback chain.
 *
 * For a given `code` on a given tenant, walks `TenantLocale.fallback`
 * pointers until either (a) the chain hits the tenant's default locale
 * (which has no fallback by invariant) or (b) the next link is missing /
 * deactivated. Returns the chain as an ordered list of codes — most
 * specific first, default last.
 *
 * The resolution is per-request memoised through `$cache` (an in-memory
 * map keyed by `tenantId|code`). A request typically resolves the same
 * locale dozens of times when serialising long product lists, so even
 * one round-trip saved per chain step matters.
 *
 * Redis-backed cross-request caching is out of scope here — the spec's
 * 50ms p95 budget for 200k SKU × 4 locales is achievable with the in-
 * memory map alone, and a Redis layer would add an invalidation surface
 * (`LocaleDeactivated`, `LocaleUpdated`, …) that LOC-04 should not own.
 */
final class LocaleFallbackResolver implements LocaleFallbackResolverInterface
{
    /**
     * @var array<string, list<string>>
     */
    private array $cache = [];

    public function __construct(
        private readonly TenantLocaleRepositoryInterface $tenantLocales,
    ) {
    }

    /**
     * @return list<string> chain codes — first element is `$code`, last is the default
     */
    public function resolve(string $code, Tenant $tenant): array
    {
        $cacheKey = $tenant->getId()->toRfc4122().'|'.$code;
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $chain = [];
        $visited = [];
        $current = $code;
        while (true) {
            if (\in_array($current, $visited, true)) {
                // Cycle in the stored data — bail out at the repeat so we
                // do not loop forever. The integrity guards in #871 /
                // LocaleFallbackCycleDetector prevent this in practice;
                // this is defence-in-depth.
                break;
            }
            $visited[] = $current;
            $chain[] = $current;

            $row = $this->tenantLocales->findByTenantAndCode($tenant, $current);
            if (null === $row || null === $row->getFallback()) {
                break;
            }

            $fallback = $row->getFallback();
            if (!$this->fallbackIsActive($tenant, $fallback->getCode())) {
                break;
            }

            $current = $fallback->getCode();
        }

        $this->cache[$cacheKey] = $chain;

        return $chain;
    }

    /**
     * Returns the first chain entry whose code maps to an active
     * `TenantLocale` with a populated value — or null when every step
     * is empty. Pure-function-style helper used by read sites that
     * need to fall back across locales (e.g. ObjectValue rendering at
     * /api endpoint serialisation time once LOC-05 (#873) wires it up).
     *
     * @param callable(string): bool $hasValue
     */
    public function pickFirstAvailable(string $code, Tenant $tenant, callable $hasValue): ?string
    {
        foreach ($this->resolve($code, $tenant) as $candidate) {
            if ($hasValue($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function fallbackIsActive(Tenant $tenant, string $code): bool
    {
        $row = $this->tenantLocales->findByTenantAndCode($tenant, $code);

        return null !== $row && $row->isActive();
    }

    public function invalidate(): void
    {
        $this->cache = [];
    }
}
