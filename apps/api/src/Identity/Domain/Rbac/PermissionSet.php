<?php

declare(strict_types=1);

namespace App\Identity\Domain\Rbac;

/**
 * Resolved permission set for a single (User, Tenant) pair — immutable value
 * object returned by {@see \App\Identity\Application\PermissionResolver}.
 *
 * The set is the union of every permission code reachable through the user's
 * active role assignments, plus the scope arrays (locale / channel /
 * attribute_group) projected from the user's `UserRole` rows. Empty scope
 * means "no restriction" — convention shared with `App\Identity\Domain\Entity\UserRole`.
 *
 * Used by Phase 3 Voters (#664+) and the Phase 4 `<PermissionGate>` /
 * `useCanI()` frontend hook (#681). Serialised into the `/api/auth/me`
 * response so the frontend can pre-compute visibility without hitting the
 * backend on every component render.
 */
final class PermissionSet
{
    /**
     * @param list<string> $permissionCodes
     * @param list<string> $localeScope
     * @param list<string> $channelScope
     * @param list<string> $attributeGroupScope
     */
    public function __construct(
        private readonly array $permissionCodes,
        private readonly array $localeScope = [],
        private readonly array $channelScope = [],
        private readonly array $attributeGroupScope = [],
    ) {
    }

    /**
     * @return list<string>
     */
    public function getCodes(): array
    {
        return $this->permissionCodes;
    }

    public function has(string $code): bool
    {
        return \in_array($code, $this->permissionCodes, true);
    }

    /**
     * @param list<string> $codes
     */
    public function hasAll(array $codes): bool
    {
        foreach ($codes as $code) {
            if (!$this->has($code)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $codes
     */
    public function hasAny(array $codes): bool
    {
        foreach ($codes as $code) {
            if ($this->has($code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function getLocaleScope(): array
    {
        return $this->localeScope;
    }

    /**
     * @return list<string>
     */
    public function getChannelScope(): array
    {
        return $this->channelScope;
    }

    /**
     * @return list<string>
     */
    public function getAttributeGroupScope(): array
    {
        return $this->attributeGroupScope;
    }

    public function appliesToLocale(string $locale): bool
    {
        return [] === $this->localeScope || \in_array($locale, $this->localeScope, true);
    }

    public function appliesToChannel(string $channel): bool
    {
        return [] === $this->channelScope || \in_array($channel, $this->channelScope, true);
    }

    public function isEmpty(): bool
    {
        return [] === $this->permissionCodes;
    }

    /**
     * @return array{permissions: list<string>, locale_scope: list<string>, channel_scope: list<string>, attribute_group_scope: list<string>}
     */
    public function toArray(): array
    {
        return [
            'permissions' => $this->permissionCodes,
            'locale_scope' => $this->localeScope,
            'channel_scope' => $this->channelScope,
            'attribute_group_scope' => $this->attributeGroupScope,
        ];
    }
}
