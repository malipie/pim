<?php

declare(strict_types=1);

namespace App\Channel\Infrastructure;

use App\Channel\Contracts\ScopeEnumeratorInterface;
use App\Channel\Domain\LocaleCode;
use App\Channel\Domain\Repository\ChannelRepositoryInterface;
use App\Channel\Domain\Repository\TenantLocaleRepositoryInterface;
use App\Shared\Domain\Tenant;

/**
 * #1152 — enumerates a tenant's completeness scopes from the canonical
 * Channel-context stores: active {@see \App\Channel\Domain\Entity\TenantLocale}
 * rows (normalised to short codes via {@see LocaleCode}) and the tenant's
 * {@see \App\Channel\Domain\Entity\Channel} rows.
 *
 * Autowiring aliases {@see ScopeEnumeratorInterface} to this single impl.
 */
final readonly class ScopeEnumerator implements ScopeEnumeratorInterface
{
    public function __construct(
        private TenantLocaleRepositoryInterface $tenantLocales,
        private ChannelRepositoryInterface $channels,
    ) {
    }

    public function localeShortCodes(Tenant $tenant): array
    {
        $codes = [];
        foreach ($this->tenantLocales->findActiveForTenant($tenant) as $tenantLocale) {
            $codes[LocaleCode::toShort($tenantLocale->getLocale()->getCode())] = true;
        }

        return array_keys($codes);
    }

    public function channelIdsByCode(Tenant $tenant): array
    {
        $map = [];
        foreach ($this->channels->findAllForTenant($tenant) as $channel) {
            $map[$channel->getCode()] = $channel->getId()->toRfc4122();
        }

        return $map;
    }
}
