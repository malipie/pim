<?php

declare(strict_types=1);

namespace App\Export\Application\Builder\Structural;

use App\Catalog\Domain\Entity\AttributeGroup;
use App\Catalog\Domain\Entity\ObjectTypeAttributeGroup;
use App\Catalog\Domain\Repository\AttributeGroupRepositoryInterface;
use App\Channel\Contracts\LocaleCodeResolverInterface;
use App\Channel\Domain\Repository\TenantLocaleRepositoryInterface;
use App\Export\Domain\Enum\ExportEntityType;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;

/**
 * `attribute_groups` export: the attribute-group dictionary.
 *
 * One row per AttributeGroup. Localised label/description fan out across the
 * tenant's active locales (`label.pl`, `description.en`, …); an `object_types`
 * column captures which ObjectTypes each group is attached to (via the
 * `object_type_attribute_groups` junction) so a re-import can re-attach the
 * group to the same modules. Mirrors the attribute dictionary export
 * ({@see AttributesGroupsExportBuilder}) but for group definitions.
 *
 * Columns are dynamic: a new locale exports with no change here.
 */
final readonly class AttributeGroupsExportBuilder implements StructuralExportBuilderInterface
{
    public function __construct(
        private AttributeGroupRepositoryInterface $groups,
        private TenantLocaleRepositoryInterface $locales,
        private LocaleCodeResolverInterface $localeResolver,
        private EntityManagerInterface $em,
    ) {
    }

    public function supports(ExportEntityType $type): bool
    {
        return ExportEntityType::AttributeGroups === $type;
    }

    public function columns(Tenant $tenant): array
    {
        $columns = ['code'];
        foreach ($this->localeCodes($tenant) as $code) {
            $columns[] = 'label.'.$code;
        }
        foreach ($this->localeCodes($tenant) as $code) {
            $columns[] = 'description.'.$code;
        }
        $columns[] = 'icon';
        $columns[] = 'color';
        $columns[] = 'is_required_section';
        $columns[] = 'is_shared';
        $columns[] = 'position';
        $columns[] = 'object_types';
        $columns[] = 'is_built_in';

        return $columns;
    }

    public function rows(Tenant $tenant): iterable
    {
        $localeCodes = $this->localeCodes($tenant);

        foreach ($this->groups->findAllByTenant($tenant) as $group) {
            $label = $group->getLabel();
            $description = $group->getDescription() ?? [];

            $row = ['code' => $group->getCode()];
            foreach ($localeCodes as $code) {
                $row['label.'.$code] = $label[$code] ?? '';
            }
            foreach ($localeCodes as $code) {
                $row['description.'.$code] = $description[$code] ?? '';
            }
            $row['icon'] = $group->getIcon() ?? '';
            $row['color'] = $group->getColor() ?? '';
            $row['is_required_section'] = $group->isRequiredSection() ? 'true' : 'false';
            $row['is_shared'] = $group->isShared() ? 'true' : 'false';
            $row['position'] = (string) $group->getPosition();
            $row['object_types'] = implode('|', $this->objectTypeCodes($group));
            $row['is_built_in'] = $group->isSystemGroup() ? 'true' : 'false';

            yield $row;
        }
    }

    public function count(Tenant $tenant): int
    {
        return \count($this->groups->findAllByTenant($tenant));
    }

    /**
     * ObjectType codes the group is attached to, via the
     * `object_type_attribute_groups` junction.
     *
     * @return list<string>
     */
    private function objectTypeCodes(AttributeGroup $group): array
    {
        /** @var list<string> $codes */
        $codes = $this->em->createQuery(
            'SELECT ot.code FROM '.ObjectTypeAttributeGroup::class.' j JOIN j.objectType ot'
            .' WHERE j.attributeGroup = :g ORDER BY ot.code ASC',
        )->setParameter('g', $group)->getSingleColumnResult();

        return $codes;
    }

    /**
     * @return list<string>
     */
    private function localeCodes(Tenant $tenant): array
    {
        // Short codes (`pl`, not `pl_PL`): the JSONB label/description keys, the
        // workspace locales and the import grammar all use the short form, so
        // `label.{short}` is what round-trips on re-import.
        $codes = [];
        foreach ($this->locales->findActiveForTenant($tenant) as $tenantLocale) {
            $codes[$this->localeResolver->toShort($tenantLocale->getLocale()->getCode())] = true;
        }

        return array_keys($codes);
    }
}
