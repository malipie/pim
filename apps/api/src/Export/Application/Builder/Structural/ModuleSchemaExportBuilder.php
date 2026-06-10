<?php

declare(strict_types=1);

namespace App\Export\Application\Builder\Structural;

use App\Catalog\Domain\Entity\AttributeGroup;
use App\Catalog\Domain\Entity\AttributeGroupAttribute;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\Entity\ObjectTypeAttributeGroup;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Export\Domain\Enum\ExportEntityType;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;

use const JSON_THROW_ON_ERROR;

/**
 * EXR-06 (#1382) — `module_schema` export: ObjectType definitions.
 *
 * One row per (ObjectType, attribute) attachment so the full schema — which
 * attribute sits in which group on which ObjectType, with its config — is
 * captured. ObjectTypes with no attributes still emit a single descriptor row.
 *
 * Columns are derived dynamically from the model (capability flags read off
 * the entity, attribute config off the Attribute) — a new attribute type or
 * capability needs no change here.
 */
final readonly class ModuleSchemaExportBuilder implements StructuralExportBuilderInterface
{
    /** @var list<string> */
    private const array COLUMNS = [
        'object_type_code', 'object_type_name', 'kind', 'is_built_in', 'capabilities', 'schema_version',
        'group_code', 'group_display_mode',
        'attribute_code', 'attribute_type', 'required', 'position', 'relation_target', 'relation_cardinality',
    ];

    public function __construct(
        private ObjectTypeRepositoryInterface $objectTypes,
        private EntityManagerInterface $em,
    ) {
    }

    public function supports(ExportEntityType $type): bool
    {
        return ExportEntityType::ModuleSchema === $type;
    }

    public function columns(Tenant $tenant): array
    {
        return self::COLUMNS;
    }

    public function rows(Tenant $tenant): iterable
    {
        foreach ($this->objectTypes->findAllByTenant($tenant) as $objectType) {
            $base = $this->objectTypeColumns($objectType);

            $groups = $this->groupsOf($objectType);
            if ([] === $groups) {
                yield $base + $this->blankAttributeColumns();
                continue;
            }

            foreach ($groups as $junction) {
                $group = $junction->getAttributeGroup();
                $attributes = $this->attributesOf($group);
                if ([] === $attributes) {
                    yield $base + [
                        'group_code' => $group->getCode(),
                        'group_display_mode' => $junction->getDisplayMode(),
                    ] + $this->blankAttributeColumns(keepGroup: true);
                    continue;
                }

                foreach ($attributes as $member) {
                    $attribute = $member->getAttribute();
                    $cardinality = $attribute->getRelationCardinality();
                    yield $base + [
                        'group_code' => $group->getCode(),
                        'group_display_mode' => $junction->getDisplayMode(),
                        'attribute_code' => $attribute->getCode(),
                        'attribute_type' => $attribute->getType()->value,
                        'required' => $this->boolStr($member->isRequiredInGroup()),
                        'position' => (string) $member->getPosition(),
                        'relation_target' => implode('|', $attribute->getRelationTargetObjectTypeIds()),
                        'relation_cardinality' => null === $cardinality ? '' : $cardinality->value,
                    ];
                }
            }
        }
    }

    public function count(Tenant $tenant): int
    {
        $count = 0;
        foreach ($this->rows($tenant) as $ignored) {
            ++$count;
        }

        return $count;
    }

    /**
     * @return array<string, string>
     */
    private function objectTypeColumns(ObjectType $objectType): array
    {
        $capabilities = json_encode([
            'expose_to_main_menu' => $objectType->getExposeToMainMenu(),
            'is_categorizable' => $objectType->getIsCategorizable(),
            'has_multimedia' => $objectType->getHasMultimedia(),
            'has_variants' => $objectType->hasVariants(),
        ], JSON_THROW_ON_ERROR);

        return [
            'object_type_code' => $objectType->getCode(),
            'object_type_name' => $this->pickLabel($objectType->getLabel()),
            'kind' => $objectType->getKind()->value,
            'is_built_in' => $this->boolStr($objectType->isBuiltIn()),
            'capabilities' => $capabilities,
            'schema_version' => (string) $objectType->getSchemaVersion(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function blankAttributeColumns(bool $keepGroup = false): array
    {
        $blank = [
            'attribute_code' => '', 'attribute_type' => '', 'required' => '',
            'position' => '', 'relation_target' => '', 'relation_cardinality' => '',
        ];
        if ($keepGroup) {
            return $blank;
        }

        return ['group_code' => '', 'group_display_mode' => ''] + $blank;
    }

    /**
     * @return list<ObjectTypeAttributeGroup>
     */
    private function groupsOf(ObjectType $objectType): array
    {
        /** @var list<ObjectTypeAttributeGroup> $rows */
        $rows = $this->em->createQuery(
            'SELECT j FROM '.ObjectTypeAttributeGroup::class.' j JOIN j.attributeGroup g'
            .' WHERE j.objectType = :ot ORDER BY j.position ASC, g.code ASC',
        )->setParameter('ot', $objectType)->getResult();

        return $rows;
    }

    /**
     * @return list<AttributeGroupAttribute>
     */
    private function attributesOf(AttributeGroup $group): array
    {
        /** @var list<AttributeGroupAttribute> $rows */
        $rows = $this->em->createQuery(
            'SELECT j FROM '.AttributeGroupAttribute::class.' j JOIN j.attribute a'
            .' WHERE j.attributeGroup = :g ORDER BY j.position ASC, a.code ASC',
        )->setParameter('g', $group)->getResult();

        return $rows;
    }

    /**
     * @param array<string, string> $label
     */
    private function pickLabel(array $label): string
    {
        return $label['en'] ?? $label['pl'] ?? (array_values($label)[0] ?? '');
    }

    private function boolStr(bool $value): string
    {
        return $value ? 'true' : 'false';
    }
}
