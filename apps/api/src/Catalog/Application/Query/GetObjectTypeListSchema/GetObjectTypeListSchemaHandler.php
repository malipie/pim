<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\GetObjectTypeListSchema;

use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\Entity\ObjectTypeAttribute;
use App\Catalog\Domain\Repository\ObjectTypeAttributeRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;

/**
 * ULV-03 (#984) — resolves the universal list schema for an ObjectType.
 *
 * Composition:
 *   1. System columns (always-on, fixed order): `code`, `status`,
 *      `completeness`, `updatedAt`. Drives the standard list header on
 *      every ObjectType regardless of attribute configuration.
 *   2. Attribute columns where the junction
 *      ({@see ObjectTypeAttribute}) carries `show_in_list = true`, sorted
 *      by `list_position` ascending then `attribute.code` as a stable
 *      tie-breaker.
 *
 * Filterable + searchable attribute lists are derived from
 * `attribute.isFilterable` / `attribute.isSearchable` flags (the same
 * flags the Meilisearch indexer reads), filtered to the attributes
 * actually attached to this ObjectType — the universal endpoint rejects
 * filter params whose attribute is not in this set.
 *
 * Field-level 3-state attribute permissions (`restricted`/`view`/`edit`)
 * land in ULV-04b — the schema returned here is the unfiltered ground
 * truth; ULV-04b adds a per-user mask that hides `restricted` columns.
 *
 * Returns null when the ObjectType id is unknown — controller maps that
 * to 404. Cross-tenant reads are blocked at the repository layer (the
 * `TenantFilter` Doctrine extension applies on `findById`).
 */
final readonly class GetObjectTypeListSchemaHandler
{
    public function __construct(
        private ObjectTypeRepositoryInterface $objectTypes,
        private ObjectTypeAttributeRepositoryInterface $junctions,
    ) {
    }

    public function __invoke(GetObjectTypeListSchemaQuery $query): ?ObjectTypeListSchema
    {
        $objectType = $this->objectTypes->findById($query->objectTypeId);
        if (null === $objectType) {
            return null;
        }

        $junctions = $this->junctions->findByObjectType($objectType);
        $listJunctions = array_values(array_filter(
            $junctions,
            static fn (ObjectTypeAttribute $j): bool => $j->isShownInList(),
        ));
        usort(
            $listJunctions,
            static function (ObjectTypeAttribute $a, ObjectTypeAttribute $b): int {
                $cmp = $a->getListPosition() <=> $b->getListPosition();

                return 0 !== $cmp ? $cmp : $a->getAttribute()->getCode() <=> $b->getAttribute()->getCode();
            },
        );

        return new ObjectTypeListSchema(
            objectType: $this->projectObjectType($objectType),
            columns: $this->buildColumns($listJunctions),
            filterableAttributes: $this->filterFiltering($junctions),
            searchableAttributes: $this->filterSearching($junctions),
        );
    }

    /**
     * @return array{id: string, code: string, kind: string, label: array<string, string>, is_categorizable: bool, has_variants: bool, expose_to_main_menu: bool}
     */
    private function projectObjectType(ObjectType $objectType): array
    {
        return [
            'id' => $objectType->getId()->toRfc4122(),
            'code' => $objectType->getCode(),
            'kind' => $objectType->getKind()->value,
            'label' => $objectType->getLabel(),
            'is_categorizable' => $objectType->isCategorizable(),
            'has_variants' => $objectType->hasVariants(),
            'expose_to_main_menu' => $objectType->isExposedToMainMenu(),
        ];
    }

    /**
     * @param list<ObjectTypeAttribute> $listJunctions
     *
     * @return list<array{key: string, type: string, label: array<string, string>, position: int, sortable: bool, system: bool}>
     */
    private function buildColumns(array $listJunctions): array
    {
        $columns = [
            [
                'key' => 'code',
                'type' => 'system_identifier',
                'label' => ['pl' => 'Identyfikator', 'en' => 'Identifier'],
                'position' => 0,
                'sortable' => true,
                'system' => true,
            ],
            [
                'key' => 'status',
                'type' => 'system_status',
                'label' => ['pl' => 'Status', 'en' => 'Status'],
                'position' => 1,
                'sortable' => true,
                'system' => true,
            ],
            [
                'key' => 'completeness',
                'type' => 'system_completeness',
                'label' => ['pl' => 'Kompletność', 'en' => 'Completeness'],
                'position' => 2,
                'sortable' => true,
                'system' => true,
            ],
            [
                'key' => 'updatedAt',
                'type' => 'system_timestamp',
                'label' => ['pl' => 'Zmodyfikowano', 'en' => 'Modified'],
                'position' => 3,
                'sortable' => true,
                'system' => true,
            ],
        ];

        $position = \count($columns);
        foreach ($listJunctions as $junction) {
            $attribute = $junction->getAttribute();
            $columns[] = [
                'key' => $attribute->getCode(),
                'type' => $attribute->getType()->value,
                'label' => $attribute->getLabel(),
                'position' => $position++,
                'sortable' => true,
                'system' => false,
            ];
        }

        return $columns;
    }

    /**
     * @param list<ObjectTypeAttribute> $junctions
     *
     * @return list<string>
     */
    private function filterFiltering(array $junctions): array
    {
        $codes = [];
        foreach ($junctions as $junction) {
            $attribute = $junction->getAttribute();
            if ($attribute->isFilterable()) {
                $codes[] = $attribute->getCode();
            }
        }
        sort($codes);

        return array_values(array_unique($codes));
    }

    /**
     * @param list<ObjectTypeAttribute> $junctions
     *
     * @return list<string>
     */
    private function filterSearching(array $junctions): array
    {
        $codes = [];
        foreach ($junctions as $junction) {
            $attribute = $junction->getAttribute();
            if ($this->isSearchable($attribute)) {
                $codes[] = $attribute->getCode();
            }
        }
        sort($codes);

        return array_values(array_unique($codes));
    }

    /**
     * MVP heuristic — there is no `is_searchable` attribute flag yet; we
     * treat text-type filterable attributes as searchable since those are
     * the ones Meilisearch's full-text index actually scores well. A
     * dedicated flag (and per-attribute weight) can land later without
     * breaking the schema contract.
     */
    private function isSearchable(Attribute $attribute): bool
    {
        if (!$attribute->isFilterable()) {
            return false;
        }

        return match ($attribute->getType()) {
            AttributeType::Text, AttributeType::Wysiwyg => true,
            default => false,
        };
    }
}
