<?php

declare(strict_types=1);

namespace App\Identity\Application\Policy;

use App\Identity\Application\PermissionResolverInterface;
use App\Identity\Domain\Entity\User;

/**
 * RBAC-P3-009 (#672) — per-locale and per-channel scope enforcement per
 * PRD §3.6 / §3.7.
 *
 * Each user role row carries `locale_scope` and `channel_scope` arrays
 * (aggregated via `PermissionResolver` into a single
 * {@see \App\Identity\Domain\Rbac\PermissionSet}). The policy answers
 * the question *"may the caller edit/view this attribute's locale /
 * channel variant?"*.
 *
 * Wildcard convention (matches `UserRole` storage convention):
 *   - `[]`        — no restriction (the role has not narrowed scope),
 *   - `["*"]`     — explicit wildcard, same meaning as empty,
 *   - `["en"]`    — only that locale is allowed,
 *   - mixed       — union of allowed locales across the user's roles
 *                   (PermissionSet already aggregates roles).
 *
 * Channel scope behaves identically. The policy is stateless — every
 * call resolves through the cached PermissionSet — so no service-level
 * cache is needed on top.
 *
 * Atomic transaction note (per ticket discussion): when a PATCH body
 * carries multiple locale or channel variants for the same attribute,
 * the validator iterates them and rejects the whole request if any
 * variant fails — this policy returns boolean per variant; the request
 * body validator (RBAC-P3-012 #675) drives the iteration + rollback.
 */
final readonly class LocaleChannelScopePolicy
{
    public const string WILDCARD = '*';

    public function __construct(private PermissionResolverInterface $resolver)
    {
    }

    public function canEditLocale(User $user, string $locale): bool
    {
        return $this->localeAllowed(
            $this->resolver->resolve($user)->getLocaleScope(),
            $locale,
        );
    }

    public function canEditChannel(User $user, string $channel): bool
    {
        return $this->channelAllowed(
            $this->resolver->resolve($user)->getChannelScope(),
            $channel,
        );
    }

    /**
     * Combined check for the common case of *attribute value* edits that
     * carry both dimensions. Returns false on the first dimension that
     * fails, so callers don't need to compose the two booleans.
     */
    public function canEditValue(User $user, string $locale, string $channel): bool
    {
        $permissions = $this->resolver->resolve($user);

        return $this->localeAllowed($permissions->getLocaleScope(), $locale)
            && $this->channelAllowed($permissions->getChannelScope(), $channel);
    }

    /**
     * @param list<string> $scope
     */
    private function localeAllowed(array $scope, string $locale): bool
    {
        return $this->scopeAllows($scope, $locale);
    }

    /**
     * @param list<string> $scope
     */
    private function channelAllowed(array $scope, string $channel): bool
    {
        return $this->scopeAllows($scope, $channel);
    }

    /**
     * @param list<string> $scope
     */
    private function scopeAllows(array $scope, string $value): bool
    {
        if ([] === $scope || \in_array(self::WILDCARD, $scope, true)) {
            return true;
        }

        return \in_array($value, $scope, true);
    }
}
