<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use Symfony\Component\Uid\Uuid;

/**
 * VIEW-27 (#558) — per-user attribute favorite shortcut (PRD §5.1).
 *
 * Composite primary key `(user_id, attribute_id)`. `sort_order` keeps
 * the operator's preferred list order (lower = higher in the picker).
 *
 * Lives in Catalog (not Identity) because favorites are keyed by
 * `Attribute`; deptrac forbids Identity_Internals → Catalog_Internals.
 * `user_id` is stored as a raw UUID — same pattern as
 * `SavedView.user_id` / `SmartFilterPreset.user_id`. The DB FK still
 * cascades on user delete (defined in the migration); the entity just
 * does not hold a User reference.
 */
class UserFilterFavorite
{
    private Uuid $userId;
    private Attribute $attribute;
    private int $sortOrder;

    public function __construct(Uuid $userId, Attribute $attribute, int $sortOrder)
    {
        $this->userId = $userId;
        $this->attribute = $attribute;
        $this->sortOrder = $sortOrder;
    }

    public function getUserId(): Uuid
    {
        return $this->userId;
    }

    public function getAttribute(): Attribute
    {
        return $this->attribute;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function changeSortOrder(int $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }
}
