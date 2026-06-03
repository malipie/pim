<?php

declare(strict_types=1);

namespace App\Channel\Contracts;

use App\Shared\Domain\Tenant;

/**
 * Public contract for locale fallback chain resolution.
 *
 * Placed in Channel\Contracts so that other BCs (Catalog, Asset, …)
 * can depend on this interface without reaching into Channel internals.
 * The concrete implementation lives in Channel\Application\Locale\LocaleFallbackResolver.
 *
 * @see \App\Channel\Application\Locale\LocaleFallbackResolver
 */
interface LocaleFallbackResolverInterface
{
    /**
     * Returns the fallback chain for `$code` on `$tenant` — most specific
     * locale first, default locale last.
     *
     * @return list<string>
     */
    public function resolve(string $code, Tenant $tenant): array;

    /**
     * Returns the first chain entry for which `$hasValue($candidate)` is true,
     * or null when every chain step is empty.
     *
     * @param callable(string): bool $hasValue
     */
    public function pickFirstAvailable(string $code, Tenant $tenant, callable $hasValue): ?string;
}
