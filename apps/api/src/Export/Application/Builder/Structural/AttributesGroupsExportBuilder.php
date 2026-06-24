<?php

declare(strict_types=1);

namespace App\Export\Application\Builder\Structural;

use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\AttributeGroupAttribute;
use App\Catalog\Domain\Entity\ObjectTypeAttribute;
use App\Catalog\Domain\Repository\AttributeOptionRepositoryInterface;
use App\Catalog\Domain\Repository\AttributeRepositoryInterface;
use App\Channel\Domain\Repository\TenantLocaleRepositoryInterface;
use App\Export\Domain\Enum\ExportEntityType;
use App\Shared\Domain\Tenant;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;

use const JSON_THROW_ON_ERROR;

/**
 * EXR-06 (#1382) — `attributes_groups` export: the attribute dictionary.
 *
 * One row per attribute. Localised label/help fan out across the tenant's
 * active locales (`label.pl`, `label.en`, …); a joined `groups` column
 * captures the attribute→group membership and an `object_types` column the
 * attribute→ObjectType assignment (so a re-import can re-attach each attribute
 * to the same modules).
 *
 * Columns are dynamic: a new attribute type, locale, or select option exports
 * with no change here.
 */
final readonly class AttributesGroupsExportBuilder implements StructuralExportBuilderInterface
{
    public function __construct(
        private AttributeRepositoryInterface $attributes,
        private AttributeOptionRepositoryInterface $options,
        private TenantLocaleRepositoryInterface $locales,
        private EntityManagerInterface $em,
    ) {
    }

    public function supports(ExportEntityType $type): bool
    {
        return ExportEntityType::AttributesGroups === $type;
    }

    public function columns(Tenant $tenant): array
    {
        $columns = ['code', 'type'];
        foreach ($this->localeCodes($tenant) as $code) {
            $columns[] = 'label.'.$code;
        }
        foreach ($this->localeCodes($tenant) as $code) {
            $columns[] = 'help.'.$code;
        }
        $columns[] = 'validation_rules';
        $columns[] = 'is_localizable';
        $columns[] = 'is_scopable';
        $columns[] = 'options';
        $columns[] = 'groups';
        $columns[] = 'object_types';
        $columns[] = 'is_built_in';
        $columns[] = 'created_at';

        return $columns;
    }

    public function rows(Tenant $tenant): iterable
    {
        $localeCodes = $this->localeCodes($tenant);

        foreach ($this->attributes->findAllByTenant($tenant) as $attribute) {
            $label = $attribute->getLabel();
            $help = $attribute->getHelp() ?? [];

            $row = ['code' => $attribute->getCode(), 'type' => $attribute->getType()->value];
            foreach ($localeCodes as $code) {
                $row['label.'.$code] = $label[$code] ?? '';
            }
            foreach ($localeCodes as $code) {
                $row['help.'.$code] = $help[$code] ?? '';
            }
            $row['validation_rules'] = json_encode($attribute->getValidationRules(), JSON_THROW_ON_ERROR);
            $row['is_localizable'] = $attribute->isLocalizable() ? 'true' : 'false';
            $row['is_scopable'] = $attribute->isScopable() ? 'true' : 'false';
            $row['options'] = $this->optionsJson($attribute);
            $row['groups'] = implode('|', $this->groupCodes($attribute));
            $row['object_types'] = implode('|', $this->objectTypeCodes($attribute));
            $row['is_built_in'] = $attribute->isSystem() ? 'true' : 'false';
            $row['created_at'] = $attribute->getCreatedAt()->format(DateTimeInterface::ATOM);

            yield $row;
        }
    }

    public function count(Tenant $tenant): int
    {
        return \count($this->attributes->findAllByTenant($tenant));
    }

    private function optionsJson(Attribute $attribute): string
    {
        if (!$attribute->usesOptions()) {
            return '';
        }
        $options = [];
        foreach ($this->options->findByAttribute($attribute) as $option) {
            $options[] = ['code' => $option->getCode(), 'label' => $option->getLabel()];
        }

        return json_encode($options, JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<string>
     */
    private function groupCodes(Attribute $attribute): array
    {
        /** @var list<string> $codes */
        $codes = $this->em->createQuery(
            'SELECT g.code FROM '.AttributeGroupAttribute::class.' j JOIN j.attributeGroup g'
            .' WHERE j.attribute = :a ORDER BY g.code ASC',
        )->setParameter('a', $attribute)->getSingleColumnResult();

        return $codes;
    }

    /**
     * ObjectType codes the attribute is assigned to, via the
     * `object_type_attributes` junction. Pipe-joined into the `object_types`
     * column so a re-import can re-attach the attribute to the same modules.
     *
     * @return list<string>
     */
    private function objectTypeCodes(Attribute $attribute): array
    {
        /** @var list<string> $codes */
        $codes = $this->em->createQuery(
            'SELECT ot.code FROM '.ObjectTypeAttribute::class.' j JOIN j.objectType ot'
            .' WHERE j.attribute = :a ORDER BY ot.code ASC',
        )->setParameter('a', $attribute)->getSingleColumnResult();

        return $codes;
    }

    /**
     * @return list<string>
     */
    private function localeCodes(Tenant $tenant): array
    {
        $codes = [];
        foreach ($this->locales->findActiveForTenant($tenant) as $tenantLocale) {
            $codes[] = $tenantLocale->getLocale()->getCode();
        }

        return $codes;
    }
}
