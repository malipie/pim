<?php

declare(strict_types=1);

namespace App\Catalog\Application\Service;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Catalog\Domain\Service\EffectiveAttributeGroupResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * CHC-05 (#1287) — quantifies the blast radius of moving a master category:
 * how many products sit in the subtree, and whether their effective schema
 * (attribute-group set) would change under the new parent.
 *
 * Schema delta uses {@see EffectiveAttributeGroupResolver::resolveForCategoryPreview()}
 * anchored at the current vs. target parent — the difference is exactly the
 * groups a product inherits through the ancestor chain that the move alters.
 */
final readonly class CategoryMoveImpactCalculator
{
    public function __construct(
        private EntityManagerInterface $em,
        private EffectiveAttributeGroupResolver $resolver,
        private CatalogObjectRepositoryInterface $catalogObjects,
    ) {
    }

    /**
     * @return array{
     *     affectedObjectsCount: int,
     *     schemaWillChange: bool,
     *     addedGroupLabels: list<array<string, string>>,
     *     removedGroupLabels: list<array<string, string>>
     * }
     */
    public function calculate(CatalogObject $category, ?Uuid $targetParentId): array
    {
        $affected = $this->countAffectedProducts($category);

        $added = [];
        $removed = [];
        $targetType = $category->getCategoryTargetObjectType();
        if (null !== $targetType) {
            $targetParent = null === $targetParentId ? null : $this->catalogObjects->findById($targetParentId);

            $currentGroups = $this->resolver->resolveForCategoryPreview($targetType, $category->getParent());
            $targetGroups = $this->resolver->resolveForCategoryPreview($targetType, $targetParent);

            $currentIds = $this->idSet($currentGroups);
            $targetIds = $this->idSet($targetGroups);

            foreach ($targetGroups as $group) {
                if (!isset($currentIds[$group->getId()->toRfc4122()])) {
                    $added[] = $group->getLabel();
                }
            }
            foreach ($currentGroups as $group) {
                if (!isset($targetIds[$group->getId()->toRfc4122()])) {
                    $removed[] = $group->getLabel();
                }
            }
        }

        return [
            'affectedObjectsCount' => $affected,
            'schemaWillChange' => [] !== $added || [] !== $removed,
            'addedGroupLabels' => $added,
            'removedGroupLabels' => $removed,
        ];
    }

    private function countAffectedProducts(CatalogObject $category): int
    {
        $path = $category->getPath();
        $tenant = $category->getTenant();
        if (null === $path || '' === $path || null === $tenant) {
            return 0;
        }

        // tenant-safe: explicit tenant_id filter on the joined category rows.
        // Counts distinct products assigned to the moving category or any of
        // its descendant categories (subtree via ltree `<@`).
        $count = $this->em->getConnection()->fetchOne(
            'SELECT COUNT(DISTINCT oc.object_id) FROM object_categories oc'
            .' JOIN objects c ON c.id = oc.category_id'
            .' WHERE c.tenant_id = CAST(:tenant AS uuid) AND c.path <@ CAST(:path AS ltree)',
            ['tenant' => $tenant->getId()->toRfc4122(), 'path' => $path],
        );

        return is_numeric($count) ? (int) $count : 0;
    }

    /**
     * @param list<\App\Catalog\Domain\Entity\AttributeGroup> $groups
     *
     * @return array<string, true>
     */
    private function idSet(array $groups): array
    {
        $set = [];
        foreach ($groups as $group) {
            $set[$group->getId()->toRfc4122()] = true;
        }

        return $set;
    }
}
