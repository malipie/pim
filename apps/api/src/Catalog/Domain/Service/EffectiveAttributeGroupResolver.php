<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Service;

use App\Catalog\Domain\Entity\AttributeGroup;
use App\Catalog\Domain\Entity\AttributeGroupAttribute;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\CategoryAttributeGroup;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\Entity\ObjectTypeAttributeGroup;
use App\Catalog\Domain\ObjectKind;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * UI-08.4 (#259) — resolves the *effective* list of AttributeGroups for an
 * object's form schema by layering three sources per ADR-012 + plan §3.5:
 *
 *   1. **Globalne grupy ObjectType** (`object_type_attribute_groups`) — the
 *      baseline list every object of a given kind sees, irrespective of
 *      category. Includes the auto-attached audit group seeded by UI-08.3.
 *   2. **Grupy dziedziczone z drzewa kategorii** (`category_attribute_groups`)
 *      — for `kind='category'` objects, walked from root → leaf via the
 *      `parent` self-reference. Each ancestor contributes the groups
 *      declared with `target_object_type_id = <object's ObjectType>`.
 *   3. **Grupy declared on the object's *own* category-level ancestors**
 *      when the object IS a category — same walk, but uses the object's
 *      own `target_object_type_id` so the form shows what attributes a
 *      future child would inherit (see {@see resolveForCategoryPreview}).
 *
 * **Out of scope (Faza 1+ kandydat):**
 *   - Per-object ad-hoc groups (no `object_attribute_groups` table yet).
 *   - Product → category linkage via Association rows. Products in MVP
 *     surface only their ObjectType groups; category-driven inheritance
 *     for products lands when {@see Association} is used as the canonical
 *     product↔category edge (see plan §14 open questions).
 *
 * **Deduplication:** outputs are unique by `AttributeGroup.id`. When the
 * same group is declared at multiple levels, the higher-priority source
 * wins (ObjectType global beats category, root category beats descendant
 * — first occurrence kept). Order within the result list follows source
 * priority + `position`.
 *
 * **Thread safety / caching:** the service is stateless; cache is wrapped
 * around it by {@see \App\Catalog\Application\Query\GetObjectFormSchema\GetObjectFormSchemaHandler}
 * via the modeling cache pool. The resolver itself never caches — it
 * remains the source of truth for tests + invalidation paths.
 */
final readonly class EffectiveAttributeGroupResolver
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Resolve effective groups for an existing object's form.
     *
     * @return list<AttributeGroup>
     */
    public function resolve(CatalogObject $object): array
    {
        $type = $object->getObjectType();

        $groups = $this->loadObjectTypeGroups($type);

        if (ObjectKind::Category === $object->getKind()) {
            // Walk the object's own ancestor chain to gather declared groups
            // targeting category itself (preview semantics: "groups visible
            // when editing a category sitting on this branch").
            $ancestorIds = $this->collectCategoryAncestorIds($object);
            $this->mergeCategoryGroups($groups, $ancestorIds, $type);
        }

        return array_values($groups);
    }

    /**
     * Resolve effective groups for a hypothetical new object placed under
     * an optional category anchor — used by the admin "preview" panel
     * before the row exists (#UI-08.10 wizard, #UI-08.14 inheritance
     * preview).
     *
     * @return list<AttributeGroup>
     */
    public function resolveForCategoryPreview(ObjectType $type, ?CatalogObject $categoryAnchor): array
    {
        $groups = $this->loadObjectTypeGroups($type);

        if (null !== $categoryAnchor && ObjectKind::Category === $categoryAnchor->getKind()) {
            $ancestorIds = $this->collectCategoryAncestorIds($categoryAnchor, includeSelf: true);
            $this->mergeCategoryGroups($groups, $ancestorIds, $type);
        }

        return array_values($groups);
    }

    /**
     * @return array<string, AttributeGroup> keyed by group UUID for dedup
     */
    private function loadObjectTypeGroups(ObjectType $type): array
    {
        /** @var list<ObjectTypeAttributeGroup> $junctions */
        $junctions = $this->em
            ->createQuery(
                'SELECT j, g FROM '.ObjectTypeAttributeGroup::class.' j'
                .' JOIN j.attributeGroup g'
                .' WHERE j.objectType = :type'
                .' ORDER BY j.position ASC, g.code ASC'
            )
            ->setParameter('type', $type)
            ->getResult();

        $groups = [];
        foreach ($junctions as $junction) {
            $group = $junction->getAttributeGroup();
            $groups[$group->getId()->toRfc4122()] = $group;
        }

        return $groups;
    }

    /**
     * @param array<string, AttributeGroup> $groups      in/out — extended with category-derived groups (first occurrence wins)
     * @param list<Uuid>                    $categoryIds ordered root → leaf
     */
    private function mergeCategoryGroups(array &$groups, array $categoryIds, ObjectType $targetType): void
    {
        if ([] === $categoryIds) {
            return;
        }

        /** @var list<CategoryAttributeGroup> $junctions */
        $junctions = $this->em
            ->createQuery(
                'SELECT j, g FROM '.CategoryAttributeGroup::class.' j'
                .' JOIN j.attributeGroup g'
                .' WHERE j.categoryObjectId IN (:ids)'
                .' AND j.targetObjectType = :type'
                .' ORDER BY j.position ASC, g.code ASC'
            )
            ->setParameter('ids', array_map(static fn (Uuid $u): string => $u->toRfc4122(), $categoryIds))
            ->setParameter('type', $targetType)
            ->getResult();

        foreach ($junctions as $junction) {
            $group = $junction->getAttributeGroup();
            $key = $group->getId()->toRfc4122();
            if (isset($groups[$key])) {
                continue;
            }
            $groups[$key] = $group;
        }
    }

    /**
     * Walk the parent chain of a category, returning ancestor ids in
     * root → leaf order. Stops at the first non-category parent (the tree
     * mixes kinds via `parent_id` for variants).
     *
     * @return list<Uuid>
     */
    private function collectCategoryAncestorIds(CatalogObject $category, bool $includeSelf = false): array
    {
        $chain = [];
        $cursor = $includeSelf ? $category : $category->getParent();
        $depthGuard = 0;
        while (null !== $cursor && ObjectKind::Category === $cursor->getKind()) {
            $chain[] = $cursor->getId();
            $cursor = $cursor->getParent();
            if (++$depthGuard > 64) {
                break; // Defensive — ltree depth in practice <10.
            }
        }

        return array_reverse($chain);
    }

    /**
     * VIEW-04 (#408) — annotates each resolved AttributeGroup with the
     * source that contributed it (ObjectType-global, declared on the
     * preview anchor itself, or inherited from a specific ancestor).
     *
     * This is a thin reporting wrapper around the same layered walk
     * {@see resolveForCategoryPreview} performs; it is split out so the
     * categories detail endpoint (#UI-08.14 + VIEW-04) can render
     * "↪ {ancestorName}" badges and the form-schema handler can keep
     * its lightweight `list<AttributeGroup>` shape unchanged.
     *
     * The returned shape is keyed by AttributeGroup UUID. Each entry's
     * `source` is one of:
     *   - `'object_type'`      → globally declared on the ObjectType
     *   - `'declared_here'`    → declared directly on the preview anchor
     *   - `'inherited_from'`   → declared on a strict ancestor
     * For `inherited_from`, `sourceCategory` carries the ancestor's
     * id + path + display name (the {@see CatalogObject} itself is not
     * exposed to keep the shape persistence-free).
     *
     * @return array<string, array{source: 'object_type'|'declared_here'|'inherited_from', sourceCategory: ?array{id: string, code: string, path: ?string}}>
     */
    public function buildSourceMap(ObjectType $type, ?CatalogObject $categoryAnchor): array
    {
        $sources = [];

        foreach ($this->loadObjectTypeGroups($type) as $key => $_group) {
            $sources[$key] = ['source' => 'object_type', 'sourceCategory' => null];
        }

        if (null === $categoryAnchor || ObjectKind::Category !== $categoryAnchor->getKind()) {
            return $sources;
        }

        // Build a per-ancestor list ordered root → leaf (anchor last).
        // We need each ancestor as the *full* CatalogObject to render
        // its display name in the badge — the existing helper returns
        // ids only, so we walk the parent chain again here keeping the
        // entities. The chain is short in practice (<10) so the cost
        // is negligible.
        $ancestorChain = [];
        $cursor = $categoryAnchor;
        $depthGuard = 0;
        while (null !== $cursor && ObjectKind::Category === $cursor->getKind()) {
            array_unshift($ancestorChain, $cursor);
            $cursor = $cursor->getParent();
            if (++$depthGuard > 64) {
                break;
            }
        }

        foreach ($ancestorChain as $ancestor) {
            /** @var list<CategoryAttributeGroup> $junctions */
            $junctions = $this->em
                ->createQuery(
                    'SELECT j, g FROM '.CategoryAttributeGroup::class.' j'
                    .' JOIN j.attributeGroup g'
                    .' WHERE j.categoryObjectId = :categoryId'
                    .' AND j.targetObjectType = :type'
                    .' ORDER BY j.position ASC, g.code ASC'
                )
                ->setParameter('categoryId', $ancestor->getId(), 'uuid')
                ->setParameter('type', $type)
                ->getResult();

            foreach ($junctions as $junction) {
                $group = $junction->getAttributeGroup();
                $key = $group->getId()->toRfc4122();
                if (isset($sources[$key])) {
                    // ObjectType-global wins on conflict — match the dedup
                    // policy in {@see mergeCategoryGroups}. Same for an
                    // inherited group declared on a deeper ancestor: the
                    // first occurrence (root → leaf) keeps its source.
                    continue;
                }
                $isAnchor = $ancestor === $categoryAnchor;
                $sources[$key] = [
                    'source' => $isAnchor ? 'declared_here' : 'inherited_from',
                    'sourceCategory' => $isAnchor ? null : [
                        'id' => $ancestor->getId()->toRfc4122(),
                        'code' => $ancestor->getCode(),
                        'path' => $ancestor->getPath(),
                    ],
                ];
            }
        }

        return $sources;
    }

    /**
     * Eager-load the AttributeGroupAttribute junctions for a list of
     * groups in one query. Keeps the form-schema endpoint to two round
     * trips (groups + attributes) regardless of the number of groups.
     *
     * @param list<AttributeGroup> $groups
     *
     * @return array<string, list<AttributeGroupAttribute>> keyed by group UUID
     */
    public function loadGroupAttributes(array $groups): array
    {
        if ([] === $groups) {
            return [];
        }

        /** @var list<AttributeGroupAttribute> $junctions */
        $junctions = $this->em
            ->createQuery(
                'SELECT j, a, g FROM '.AttributeGroupAttribute::class.' j'
                .' JOIN j.attribute a'
                .' JOIN j.attributeGroup g'
                .' WHERE g IN (:groups)'
                .' ORDER BY j.position ASC, a.code ASC'
            )
            ->setParameter('groups', $groups)
            ->getResult();

        $byGroup = [];
        foreach ($junctions as $junction) {
            $byGroup[$junction->getAttributeGroup()->getId()->toRfc4122()][] = $junction;
        }

        return $byGroup;
    }
}
