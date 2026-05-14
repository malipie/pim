<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Entity\UserFilterFavorite;
use Symfony\Component\Uid\Uuid;

/**
 * VIEW-27 (#558) — repository contract for the per-user favorite
 * attribute shortcut list. Composite PK so the typical `find()` shape
 * does not apply; call sites either fetch the full list for a user
 * (picker) or atomically replace it (PUT).
 *
 * `user_id` is passed as a raw UUID rather than a User entity so the
 * repository (Catalog bundle) does not pull in Identity_Internals.
 */
interface UserFilterFavoriteRepositoryInterface
{
    /**
     * Full ordered list (by sort_order ASC then attribute_id ASC) for
     * a single user. Empty list when the user has no favorites yet.
     *
     * @return list<UserFilterFavorite>
     */
    public function findByUser(Uuid $userId): array;

    /**
     * Atomic replace of the entire list for a user. Wipes existing rows
     * and inserts the supplied (attribute, sort_order) tuples in the
     * same transaction.
     *
     * @param list<array{attribute_id: string, sort_order: int}> $entries
     */
    public function replaceForUser(Uuid $userId, array $entries): void;

    public function save(UserFilterFavorite $favorite): void;
}
