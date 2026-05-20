<?php

declare(strict_types=1);

namespace App\Identity\Domain\Entity;

use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

/**
 * RBAC-P5-007 (#697) — per-attribute permission override on a role,
 * implementing the 3-state grant scheme from PRD-PIM-rbac §3.5.
 *
 * Resolution order (consumed by Phase 3 AttributePermissionPolicy, not
 * yet wired — that's the cross-tab sync deferred to a follow-up):
 *   1. If a row exists for (role_id, attribute_id) → use its level.
 *   2. Otherwise fall back to the role's module-level grant (the
 *      checkbox matrix from #696).
 *
 * Storing `restricted` explicitly (rather than omitting the row) is
 * intentional: it lets an operator override a role that has `edit`
 * on the matrix for a whole module but should NOT touch a single
 * sensitive attribute (e.g. `cost_price` on Products). Without the
 * explicit row, the matrix grant would always win.
 *
 * No FK to {@see \App\Catalog\Domain\Entity\Attribute} because Catalog
 * owns that table and cross-bundle FKs leak bounded-context boundaries;
 * the write path validates attribute existence via the repository.
 */
class RoleAttributePermission
{
    public const string LEVEL_VIEW = 'view';
    public const string LEVEL_EDIT = 'edit';
    public const string LEVEL_RESTRICTED = 'restricted';

    private const array LEVELS = [self::LEVEL_VIEW, self::LEVEL_EDIT, self::LEVEL_RESTRICTED];

    private Uuid $id;

    private Uuid $roleId;

    private Uuid $attributeId;

    private string $permissionLevel;

    private DateTimeImmutable $createdAt;

    private DateTimeImmutable $updatedAt;

    public function __construct(
        Uuid $roleId,
        Uuid $attributeId,
        string $permissionLevel,
        ?Uuid $id = null,
        ?DateTimeImmutable $createdAt = null,
    ) {
        self::assertLevel($permissionLevel);
        $now = $createdAt ?? new DateTimeImmutable();
        $this->id = $id ?? Uuid::v7();
        $this->roleId = $roleId;
        $this->attributeId = $attributeId;
        $this->permissionLevel = $permissionLevel;
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getRoleId(): Uuid
    {
        return $this->roleId;
    }

    public function getAttributeId(): Uuid
    {
        return $this->attributeId;
    }

    public function getPermissionLevel(): string
    {
        return $this->permissionLevel;
    }

    public function setPermissionLevel(string $level): void
    {
        self::assertLevel($level);
        $this->permissionLevel = $level;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return list<string>
     */
    public static function levels(): array
    {
        return self::LEVELS;
    }

    private static function assertLevel(string $level): void
    {
        if (!\in_array($level, self::LEVELS, true)) {
            throw new InvalidArgumentException(\sprintf(
                'Invalid permission level `%s`. Expected one of: %s',
                $level,
                implode(', ', self::LEVELS),
            ));
        }
    }
}
