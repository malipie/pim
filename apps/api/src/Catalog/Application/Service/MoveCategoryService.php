<?php

declare(strict_types=1);

namespace App\Catalog\Application\Service;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * VIEW-04 (#408) — atomic re-parenting of a category subtree on
 * `PATCH /api/categories/{id}/move`.
 *
 * Use case: operator drags Ortopeda from Chirurg → Pediatra. The old
 * path is `service.lekarz.chirurg.ortopeda`; new path becomes
 * `service.lekarz.pediatra.ortopeda` — and **every descendant** of
 * Ortopeda follows with a path-prefix rewrite.
 *
 * Why not Doctrine + per-entity flush? Updating an N-deep subtree row
 * by row in PHP would be both slow and a memory hazard in worker mode.
 * The service does the rewrite as a single SQL `UPDATE` over the
 * `objects` table inside one transaction, then evicts the affected
 * entities from the `EntityManager` so subsequent reads re-hydrate the
 * fresh paths. The aggregate's `parent_id` is updated through the
 * domain setter so the audit trail (in dh_auditor for the parent row
 * + Mercure publisher in phase 1) sees the change normally.
 *
 * Invariants enforced:
 *   - Subject must be a `kind=category` CatalogObject.
 *   - New parent (when not null) must also be a category in the same
 *     tenant.
 *   - New parent cannot live inside the moving subtree (cycle
 *     detection: `parent.path <@ moving.path` is forbidden).
 *   - Self-move (newParent == current parent) returns no-op affected=0.
 *
 * Returns the number of descendants whose paths were rewritten so the
 * controller can echo it back for telemetry.
 */
final class MoveCategoryService
{
    public function __construct(
        private readonly CatalogObjectRepositoryInterface $catalogObjects,
        private readonly EntityManagerInterface $em,
        private readonly Connection $connection,
    ) {
    }

    public function move(CatalogObject $category, ?Uuid $newParentId): int
    {
        if (ObjectKind::Category !== $category->getKind()) {
            throw new UnprocessableEntityHttpException(\sprintf(
                'Object "%s" is not a category and cannot be moved.',
                $category->getId()->toRfc4122(),
            ));
        }

        $oldPath = $category->getPath();
        if (null === $oldPath || '' === $oldPath) {
            throw new UnprocessableEntityHttpException(
                'Category has no ltree path; cannot be moved before it is initialised.',
            );
        }

        $newParent = null;
        if (null !== $newParentId) {
            $newParent = $this->catalogObjects->findById($newParentId);
            if (null === $newParent) {
                throw new NotFoundHttpException(\sprintf(
                    'Parent category "%s" was not found.',
                    $newParentId->toRfc4122(),
                ));
            }
            if (ObjectKind::Category !== $newParent->getKind()) {
                throw new UnprocessableEntityHttpException(\sprintf(
                    'Object "%s" is not a category and cannot be a parent.',
                    $newParentId->toRfc4122(),
                ));
            }
            if ($newParent->getId()->toRfc4122() === $category->getId()->toRfc4122()) {
                throw new UnprocessableEntityHttpException('Category cannot be its own parent.');
            }
            $parentPath = $newParent->getPath();
            if (null === $parentPath || '' === $parentPath) {
                throw new UnprocessableEntityHttpException(
                    'New parent category has no ltree path.',
                );
            }
            // Cycle detection — moving a node inside its own subtree is
            // illegal because it would form an unreachable loop. Use the
            // `<@` ltree operator which returns true when the left side
            // is a descendant-or-equal of the right.
            $isInsideSubtree = $this->connection->fetchOne(
                'SELECT 1 WHERE CAST(:parentPath AS ltree) <@ CAST(:movingPath AS ltree)',
                ['parentPath' => $parentPath, 'movingPath' => $oldPath],
            );
            if (false !== $isInsideSubtree) {
                throw new UnprocessableEntityHttpException(\sprintf(
                    'Cannot move category to its own descendant "%s".',
                    $newParent->getCode(),
                ));
            }
        }

        $newPath = $this->computeNewPath($newParent, $category);
        if ($newPath === $oldPath) {
            // No-op — short-circuit before opening a transaction.
            return 0;
        }

        $this->connection->beginTransaction();
        try {
            // Rewrite the moving node first.
            $this->connection->executeStatement(
                'UPDATE objects SET path = CAST(:newPath AS ltree), parent_id = :parentId, updated_at = NOW() WHERE id = CAST(:id AS uuid)',
                [
                    'newPath' => $newPath,
                    'parentId' => null === $newParent ? null : $newParent->getId()->toRfc4122(),
                    'id' => $category->getId()->toRfc4122(),
                ],
                [
                    'parentId' => null === $newParent ? Types::STRING : Types::STRING,
                ],
            );

            // Rewrite descendants — strict descendants only (excluding self,
            // which we just rewrote). LTREE `subpath(path, oldDepth)` strips
            // the old prefix; concatenation with `newPath ||` re-anchors it
            // under the new parent path.
            $oldDepth = $this->ltreeDepth($oldPath);
            $affected = $this->connection->executeStatement(
                'UPDATE objects SET path = CAST(:newPath AS ltree) || subpath(path, :oldDepth), updated_at = NOW()'
                .' WHERE kind = :kind AND path <@ CAST(:oldPath AS ltree) AND id <> CAST(:id AS uuid)',
                [
                    'newPath' => $newPath,
                    'oldDepth' => $oldDepth,
                    'kind' => ObjectKind::Category->value,
                    'oldPath' => $oldPath,
                    'id' => $category->getId()->toRfc4122(),
                ],
                [
                    'oldDepth' => Types::INTEGER,
                ],
            );

            $this->connection->commit();
        } catch (Throwable $e) {
            $this->connection->rollBack();
            if ($e instanceof HttpException) {
                throw $e;
            }
            throw new HttpException(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'Failed to move category subtree: '.$e->getMessage(),
                $e,
            );
        }

        // Invalidate the in-memory entities so the next read picks up the
        // rewritten paths. We clear() rather than refresh() because the
        // affected set may include rows the caller never loaded.
        $this->em->clear();

        return (int) $affected;
    }

    private function computeNewPath(?CatalogObject $newParent, CatalogObject $category): string
    {
        if (null === $newParent) {
            return $category->getCode();
        }
        $parentPath = $newParent->getPath();
        if (null === $parentPath || '' === $parentPath) {
            return $category->getCode();
        }

        return $parentPath.'.'.$category->getCode();
    }

    private function ltreeDepth(string $path): int
    {
        if ('' === $path) {
            return 0;
        }

        return substr_count($path, '.') + 1;
    }
}
