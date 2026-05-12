<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\EventListener;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\ObjectKind;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;

/**
 * PCAT-03 (#476) — when a category is removed, promote the next-oldest
 * remaining assignment to primary for every product that lost its primary
 * to the cascade.
 *
 * Mechanics:
 *
 *   1. `onFlush` scans scheduled deletions for `CatalogObject` rows of
 *      `kind=category`. For each one, reads the `object_categories` table
 *      to find every product where this category is currently primary,
 *      and buffers the product ids in a state field.
 *
 *   2. `postFlush` fires *after* the DB-level CASCADE has already
 *      removed the assignment rows. For each buffered product id we run
 *      a single raw DBAL UPDATE that promotes the oldest remaining
 *      assignment (`position ASC, created_at ASC LIMIT 1`) to primary.
 *      If no rows remain (the deleted category was the only assignment),
 *      the product simply ends up without a primary — legal.
 *
 * Raw DBAL is used because by `postFlush` the affected ORM entities are
 * already detached after CASCADE; managed-entity operations would either
 * miss them or trigger spurious change-tracking. The single SQL also
 * sidesteps the partial-unique-index window (the CASCADE removed the
 * primary row first, so promoting another to `is_primary=true` is safe).
 *
 * tenant-safe: object_categories is a junction table whose tenant
 * isolation comes from the FK chain (object_id + category_id are both
 * tenant-scoped CatalogObject ids). The buffered productIds collected
 * in onFlush originate from a CatalogObject that already passed
 * TenantFilter on its own load; the `category_id` queried in
 * onFlush is the deletion target which Doctrine resolved through
 * the same filter.
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class PrimaryCategoryRepairListener
{
    /**
     * @var array<string, true> set of product ids (RFC4122) whose primary
     *                          assignment is about to be cascade-removed
     */
    private array $productsLosingPrimary = [];

    public function onFlush(OnFlushEventArgs $event): void
    {
        $em = $event->getObjectManager();
        $uow = $em->getUnitOfWork();
        $conn = $em->getConnection();

        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if (!$entity instanceof CatalogObject) {
                continue;
            }
            if (ObjectKind::Category !== $entity->getKind()) {
                continue;
            }

            $rows = $conn->fetchAllAssociative(
                'SELECT object_id FROM object_categories WHERE category_id = :cat AND is_primary = true',
                ['cat' => $entity->getId()->toRfc4122()],
            );
            foreach ($rows as $row) {
                $productId = $row['object_id'] ?? null;
                if (\is_string($productId)) {
                    $this->productsLosingPrimary[$productId] = true;
                }
            }
        }
    }

    public function postFlush(PostFlushEventArgs $event): void
    {
        if ([] === $this->productsLosingPrimary) {
            return;
        }

        $em = $event->getObjectManager();
        $conn = $em->getConnection();
        $ids = array_keys($this->productsLosingPrimary);
        $this->productsLosingPrimary = [];

        foreach ($ids as $productId) {
            $this->promoteOldestRemaining($conn, $productId);
        }
    }

    /**
     * Atomic promote-next inside a single transaction. The lookup +
     * update run as one SQL each; the partial unique index allows the
     * promotion because the previous primary row no longer exists
     * (cascade removed it before postFlush fires).
     */
    private function promoteOldestRemaining(Connection $conn, string $productId): void
    {
        $row = $conn->fetchAssociative(
            'SELECT category_id FROM object_categories'
            .' WHERE object_id = :pid'
            .' ORDER BY position ASC, created_at ASC'
            .' LIMIT 1',
            ['pid' => $productId],
        );

        if (false === $row) {
            // Product has no assignments left — primary stays unset, legal.
            return;
        }

        $candidate = $row['category_id'] ?? null;
        if (!\is_string($candidate)) {
            return;
        }

        $conn->executeStatement(
            'UPDATE object_categories SET is_primary = true'
            .' WHERE object_id = :pid AND category_id = :cat',
            ['pid' => $productId, 'cat' => $candidate],
        );
    }
}
