<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Domain\Entity\CatalogObject;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * ADR-015 — guards object↔category assignment against cross-tree mixing.
 *
 * Each category belongs to exactly one categorizable ObjectType's tree
 * (`category_target_object_type_id`). An object may only be filed under
 * categories from its own ObjectType's tree, so a product cannot be
 * assigned a "Salony sprzedaży" category and vice-versa.
 *
 * This is defense-in-depth: the modeling UI already lists only same-tree
 * categories (PR-C filter), but the API must reject a hand-crafted
 * cross-tree assignment with a clean 422 instead of silently persisting
 * an attribute-distribution mismatch.
 */
final class CategoryTreeAssignmentGuard
{
    public function assertSameTree(CatalogObject $object, CatalogObject $category): void
    {
        $tree = $category->getCategoryTargetObjectType();
        if (null === $tree || !$tree->getId()->equals($object->getObjectType()->getId())) {
            throw new UnprocessableEntityHttpException(\sprintf(
                'Category "%s" belongs to a different ObjectType tree and cannot be assigned to a "%s" object.',
                $category->getCode(),
                $object->getObjectType()->getCode(),
            ));
        }
    }
}
