<?php

declare(strict_types=1);

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Entity\UserFilterFavorite;

/**
 * VIEW-27 (#558) — repository contract for the per-user favorite
 * attribute shortcut list. Composite PK so the typical `find()` shape
 * does not apply; call sites either fetch the full list for a user
 * (picker) or atomically replace it (PUT).
 */
interface UserFilterFavoriteRepositoryInterface
{
    /**
     * Full ordered list (by sort_order ASC then attribute_id ASC) for
     * a single user. Empty list when the user has no favorites yet.
     *
     * @return list<UserFilterFavorite>
     */
    public function findByUser(User $user): array;

    /**
     * Atomic replace of the entire list for a user. Wipes existing
     * rows and inserts the supplied (attribute, sort_order) tuples in
     * the same transaction.
     *
     * @param list<array{attribute_id: string, sort_order: int}> $entries
     */
    public function replaceForUser(User $user, array $entries): void;

    public function save(UserFilterFavorite $favorite): void;
}
