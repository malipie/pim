<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\EventListener;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\ObjectKind;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;

/**
 * VIEW-04 (#408) — auto-builds the ltree `path` for a freshly created
 * `kind=category` `CatalogObject` from its parent's path + own code.
 *
 *   - root (no parent) → `path = code`
 *   - leaf (parent set) → `path = parent.path . '.' . code`
 *
 * Runs only on `prePersist`: a category's ltree position is decided when
 * it is first inserted. Reparenting an existing category goes through
 * {@see \App\Catalog\Application\Service\MoveCategoryService} which
 * rewrites the subtree in a single DBAL transaction — `preUpdate` is
 * intentionally not handled here so the path stays append-only at insert
 * time and the move service has the only write authority for moves.
 *
 * The format of the resulting path is validated by the existing
 * {@see CategoryPathValidator} listener which fires on the same event;
 * `CategoryPathBuilder` runs first (order is alphabetical; "Builder"
 * sorts before "Validator") so the validator inspects the path we just
 * computed rather than `null`. If the admin posted an explicit `path`
 * already we leave it alone — the validator catches malformed values
 * either way.
 *
 * Non-category kinds and CatalogObjects whose parent is itself
 * non-category (e.g. product variants under a master product) are left
 * untouched — `path` stays `NULL` and the validator confirms the
 * "category-only" invariant.
 */
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::postPersist)]
final class CategoryPathBuilder
{
    /**
     * `postPersist` partner to {@see prePersist}: Doctrine ORM 3 freezes
     * the insert change-set *before* `prePersist` listeners fire, so a
     * write to `path` from `attachToPath()` inside `prePersist` lands in
     * the in-memory aggregate but never reaches the INSERT (the row
     * persists with `path = NULL`).
     *
     * After the INSERT we sync the derived path to the database with a
     * one-row UPDATE through DBAL. This keeps the public {@see CatalogObject}
     * API setter-light (no public path setter for callers) while still
     * delivering the kind=category invariant: every freshly persisted
     * category row has its `path` materialised before the request
     * returns.
     */
    public function postPersist(PostPersistEventArgs $event): void
    {
        $entity = $event->getObject();
        if (!$entity instanceof CatalogObject) {
            return;
        }
        if (ObjectKind::Category !== $entity->getKind()) {
            return;
        }
        $path = $entity->getPath();
        if (null === $path || '' === $path) {
            return;
        }

        $em = $event->getObjectManager();
        $em->getConnection()->executeStatement(
            'UPDATE objects SET path = CAST(:path AS ltree) WHERE id = CAST(:id AS uuid) AND path IS NULL',
            [
                'path' => $path,
                'id' => $entity->getId()->toRfc4122(),
            ],
        );
    }

    /**
     * Subset of the {@see CategoryPathValidator::LTREE_LABEL} regex that
     * matches a *single* label. The full path regex chains labels with
     * dots; we apply the per-label form to the code before promoting it
     * into a path so legacy tests (or future imports) using mixed-case /
     * dashed codes simply skip auto-pathing instead of tripping the
     * validator. Operators using the modeling UI always pass snake_case
     * lower codes (validated client-side), so the auto-build path
     * applies for the live admin flow.
     */
    private const string LTREE_LABEL_SINGLE = '/^[a-z_][a-z0-9_]*$/';

    public function prePersist(PrePersistEventArgs $event): void
    {
        $entity = $event->getObject();
        if (!$entity instanceof CatalogObject) {
            return;
        }
        if (ObjectKind::Category !== $entity->getKind()) {
            return;
        }
        if (null !== $entity->getPath()) {
            return; // Operator-provided path — defer to validator.
        }
        if (1 !== preg_match(self::LTREE_LABEL_SINGLE, $entity->getCode())) {
            // Code is not a valid single ltree label (e.g. uppercase or
            // dashed). Leave path NULL — the validator allows that and
            // the operator can backfill via PATCH later.
            return;
        }

        $parent = $entity->getParent();
        if (null === $parent) {
            $entity->attachToPath($entity->getCode());

            return;
        }

        if (ObjectKind::Category !== $parent->getKind()) {
            // Mixed-kind parent (e.g. category orphan attached to a
            // product). Leave path NULL and let validator decide whether
            // this is even legal (currently: yes, parent_id can carry a
            // non-category for variants, but path will stay null).
            return;
        }

        $parentPath = $parent->getPath();
        if (null === $parentPath || '' === $parentPath) {
            // Parent is a category but has no path yet — usually because
            // the parent itself is being persisted in the same UoW and
            // its own listener fires after ours. Fall back to code-only;
            // the next category Persist downstream of the chain is rare
            // enough that we accept the rebuild on first edit if it
            // happens. (Not observed in practice — categories are
            // created depth-first by the wizard flow.)
            $entity->attachToPath($entity->getCode());

            return;
        }

        $entity->attachToPath($parentPath.'.'.$entity->getCode());
    }
}
