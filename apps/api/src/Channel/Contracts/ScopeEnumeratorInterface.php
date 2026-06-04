<?php

declare(strict_types=1);

namespace App\Channel\Contracts;

use App\Shared\Domain\Tenant;

/**
 * Cross-BC port enumerating a tenant's completeness scopes (#1152).
 *
 * Exposed from `Channel\Contracts` so Catalog (the completeness rebuilder)
 * can iterate the tenant's locales + channels without reaching into Channel
 * internals (Deptrac: `Catalog_Internals -> Channel_Contracts`).
 */
interface ScopeEnumeratorInterface
{
    /**
     * Active tenant locales as SHORT codes (`pl`, `en`) — matches
     * `ObjectValue.locale` and the `per_locale` completeness keys.
     *
     * @return list<string>
     */
    public function localeShortCodes(Tenant $tenant): array;

    /**
     * Tenant channels as `code => UUID rfc4122` — the value rows key the
     * channel scope by id, the `per_channel` completeness map by code.
     *
     * @return array<string, string>
     */
    public function channelIdsByCode(Tenant $tenant): array;
}
