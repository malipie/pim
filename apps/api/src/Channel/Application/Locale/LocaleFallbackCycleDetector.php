<?php

declare(strict_types=1);

namespace App\Channel\Application\Locale;

use App\Channel\Domain\Entity\TenantLocale;
use App\Channel\Domain\Repository\TenantLocaleRepositoryInterface;
use App\Shared\Domain\Tenant;

/**
 * Locales feature (#872, LOC-04) — validates that proposed fallback
 * assignments do not create a cycle.
 *
 * Called from `TenantLocaleController` (#871) on POST / PATCH before
 * persisting `fallback_locale_id`. Walks the chain that *would* exist
 * after the assignment lands; if at any point the chain references the
 * locale being edited, the proposal is rejected.
 *
 * Self-fallback is rejected at the entity level by `TenantLocale`
 * (DomainException + DB CHECK `tenant_locales_no_self_fallback`); the
 * detector only deals with N-cycles where N ≥ 2.
 */
final class LocaleFallbackCycleDetector
{
    public function __construct(
        private readonly TenantLocaleRepositoryInterface $tenantLocales,
    ) {
    }

    public function wouldCreateCycle(
        string $forCode,
        ?string $newFallbackCode,
        Tenant $tenant,
    ): bool {
        if (null === $newFallbackCode || '' === $newFallbackCode) {
            return false;
        }

        if ($forCode === $newFallbackCode) {
            return true;
        }

        $visited = [$forCode => true];
        $current = $newFallbackCode;

        while (true) {
            if (isset($visited[$current])) {
                return true;
            }
            $visited[$current] = true;

            $row = $this->tenantLocales->findByTenantAndCode($tenant, $current);
            if (null === $row) {
                return false;
            }
            $fallback = $row->getFallback();
            if (null === $fallback) {
                return false;
            }

            $current = $fallback->getCode();
        }
    }
}
