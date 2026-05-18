<?php

declare(strict_types=1);

namespace App\Identity\Domain\Entity;

use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Per-user role assignment with scope (locale + channel + attribute_groups).
 *
 * Promotes the simple M2M `users <-> roles` junction into a first-class entity
 * so the assignment can carry scope restrictions (PRD-PIM-rbac §3.4): a user
 * can hold the `marketing` role but only for `pl` locale and the `shopify`
 * channel, or restricted to a subset of attribute groups.
 *
 * Scope semantics (resolved by Phase 3 ProductVoter / AttributePermissionPolicy):
 *  - empty array `[]` means "no restriction" (broad scope — role applies to all
 *    locales / channels / attribute groups). NULL is not used to keep array
 *    semantics consistent in `in_array()` checks on the resolver hot path.
 *  - non-empty array means the role is restricted to listed values; permission
 *    checks intersect this set with the resource scope before granting.
 *
 * Brownfield note: existing `User.assignedRoles` M2M (table `user_roles`)
 * stays operational. This entity targets a new table `user_role_assignments`
 * to avoid colliding with the existing junction during Phase 1. A future
 * delta migration (Phase 1 ticket #644 — `delta migrations`) consolidates
 * the two paths once Phase 3 voters consume scope directly.
 */
class UserRole
{
    private Uuid $id;

    private Uuid $userId;

    private Uuid $roleId;

    /**
     * Locale scope (`['pl', 'en']`). Empty array means "all locales".
     *
     * @var list<string>
     */
    private array $localeScope;

    /**
     * Channel scope (`['shopify', 'baselinker']`). Empty array means "all channels".
     *
     * @var list<string>
     */
    private array $channelScope;

    /**
     * Attribute-group scope (UUID list of AttributeGroup IDs). Empty array
     * means "all attribute groups".
     *
     * @var list<string>
     */
    private array $attributeGroupScope;

    private DateTimeImmutable $assignedAt;

    /**
     * @param list<string> $localeScope
     * @param list<string> $channelScope
     * @param list<string> $attributeGroupScope
     */
    public function __construct(
        Uuid $userId,
        Uuid $roleId,
        array $localeScope = [],
        array $channelScope = [],
        array $attributeGroupScope = [],
        ?Uuid $id = null,
        ?DateTimeImmutable $assignedAt = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->userId = $userId;
        $this->roleId = $roleId;
        $this->localeScope = $localeScope;
        $this->channelScope = $channelScope;
        $this->attributeGroupScope = $attributeGroupScope;
        $this->assignedAt = $assignedAt ?? new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUserId(): Uuid
    {
        return $this->userId;
    }

    public function getRoleId(): Uuid
    {
        return $this->roleId;
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

    public function getAssignedAt(): DateTimeImmutable
    {
        return $this->assignedAt;
    }

    public function hasLocaleRestriction(): bool
    {
        return [] !== $this->localeScope;
    }

    public function hasChannelRestriction(): bool
    {
        return [] !== $this->channelScope;
    }

    public function hasAttributeGroupRestriction(): bool
    {
        return [] !== $this->attributeGroupScope;
    }

    public function appliesToLocale(string $locale): bool
    {
        return [] === $this->localeScope || \in_array($locale, $this->localeScope, true);
    }

    public function appliesToChannel(string $channel): bool
    {
        return [] === $this->channelScope || \in_array($channel, $this->channelScope, true);
    }

    public function appliesToAttributeGroup(string $attributeGroupId): bool
    {
        return [] === $this->attributeGroupScope || \in_array($attributeGroupId, $this->attributeGroupScope, true);
    }
}
