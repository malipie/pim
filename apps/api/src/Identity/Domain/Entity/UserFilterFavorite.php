<?php

declare(strict_types=1);

namespace App\Identity\Domain\Entity;

use App\Catalog\Domain\Entity\Attribute;

/**
 * VIEW-27 (#558) — per-user attribute favorite shortcut (PRD §5.1).
 *
 * Composite primary key `(user_id, attribute_id)`. `sort_order` is
 * the operator's preferred list order (lower = higher on the picker).
 * Tenant scope inherited via `users.tenant_id` + `attributes.tenant_id`
 * — both FK CASCADE on delete keep the table clean.
 */
class UserFilterFavorite
{
    private User $user;
    private Attribute $attribute;
    private int $sortOrder;

    public function __construct(User $user, Attribute $attribute, int $sortOrder)
    {
        $this->user = $user;
        $this->attribute = $attribute;
        $this->sortOrder = $sortOrder;
    }

    public function getUser(): User
    {
        return $this->user;
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
