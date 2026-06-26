<?php

declare(strict_types=1);

namespace App\Export\Application\Builder\Structural;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Catalog\Domain\Repository\CategoryAttributeGroupRepositoryInterface;
use App\Channel\Contracts\LocaleCodeResolverInterface;
use App\Channel\Domain\Repository\TenantLocaleRepositoryInterface;
use App\Export\Domain\Enum\ExportEntityType;
use App\Shared\Domain\Tenant;

/**
 * EXR-06 (#1382) — `categories` export: the category tree.
 *
 * One row per category (CatalogObject kind=category), emitted in DFS order by
 * ltree path so parents precede children (round-trip friendly). Localised name
 * fans out per active locale, and `assigned_groups` lists the attribute groups
 * declared on the category (the inheritance overlay).
 */
final readonly class CategoriesExportBuilder implements StructuralExportBuilderInterface
{
    public function __construct(
        private CatalogObjectRepositoryInterface $objects,
        private CategoryAttributeGroupRepositoryInterface $categoryGroups,
        private TenantLocaleRepositoryInterface $locales,
        private LocaleCodeResolverInterface $localeResolver,
    ) {
    }

    public function supports(ExportEntityType $type): bool
    {
        return ExportEntityType::Categories === $type;
    }

    public function columns(Tenant $tenant): array
    {
        $columns = ['code'];
        foreach ($this->localeCodes($tenant) as $code) {
            $columns[] = 'name.'.$code;
        }
        $columns[] = 'parent_code';
        $columns[] = 'path';
        $columns[] = 'level';
        $columns[] = 'target_object_type_code';
        $columns[] = 'assigned_groups';

        return $columns;
    }

    public function rows(Tenant $tenant): iterable
    {
        $localeCodes = $this->localeCodes($tenant);

        foreach ($this->sortedCategories($tenant) as $category) {
            $name = $this->resolveName($category);
            $path = $category->getPath();

            $row = ['code' => $category->getCode()];
            foreach ($localeCodes as $code) {
                // '*' holds a non-localised scalar name applied to every locale.
                $row['name.'.$code] = $name[$code] ?? ($name['*'] ?? '');
            }
            $row['parent_code'] = $category->getParent()?->getCode() ?? '';
            $row['path'] = $path ?? '';
            $row['level'] = null === $path || '' === $path ? '' : (string) substr_count($path, '.');
            $row['target_object_type_code'] = $category->getCategoryTargetObjectType()?->getCode() ?? '';
            $row['assigned_groups'] = implode('|', $this->assignedGroupCodes($category));

            yield $row;
        }
    }

    public function count(Tenant $tenant): int
    {
        return \count($this->objects->findByKind(ObjectKind::Category, $tenant));
    }

    /**
     * @return list<CatalogObject>
     */
    private function sortedCategories(Tenant $tenant): array
    {
        $categories = $this->objects->findByKind(ObjectKind::Category, $tenant);
        usort($categories, static fn (CatalogObject $a, CatalogObject $b): int => ($a->getPath() ?? '') <=> ($b->getPath() ?? ''));

        return $categories;
    }

    /**
     * Localised category name keyed by locale code. A non-localised scalar
     * name is returned under the '*' key (applied to every locale column).
     *
     * @return array<string, string>
     */
    private function resolveName(CatalogObject $category): array
    {
        $labelAttribute = $category->getObjectType()->getLabelAttribute();
        if (null === $labelAttribute) {
            return [];
        }
        $value = $category->getAttributesIndexed()[$labelAttribute->getCode()] ?? null;
        if (\is_array($value)) {
            $out = [];
            foreach ($value as $locale => $localized) {
                if (\is_string($locale)) {
                    $out[$locale] = \is_scalar($localized) ? (string) $localized : '';
                }
            }

            return $out;
        }

        return \is_scalar($value) ? ['*' => (string) $value] : [];
    }

    /**
     * @return list<string>
     */
    private function assignedGroupCodes(CatalogObject $category): array
    {
        $target = $category->getCategoryTargetObjectType();
        if (null === $target) {
            return [];
        }
        $codes = [];
        foreach ($this->categoryGroups->findByCategoryAndTarget($category, $target) as $assignment) {
            $codes[] = $assignment->getAttributeGroup()->getCode();
        }

        return $codes;
    }

    /**
     * @return list<string>
     */
    private function localeCodes(Tenant $tenant): array
    {
        // Short codes (`pl`, not `pl_PL`): the JSONB name keys and the import
        // grammar use the short form, so `name.{short}` is what round-trips.
        $codes = [];
        foreach ($this->locales->findActiveForTenant($tenant) as $tenantLocale) {
            $codes[$this->localeResolver->toShort($tenantLocale->getLocale()->getCode())] = true;
        }

        return array_keys($codes);
    }
}
