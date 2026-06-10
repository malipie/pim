<?php

declare(strict_types=1);

namespace App\Export\Domain\Enum;

/**
 * EXR-04 (#1380) — the kind of data an export session/profile produces.
 *
 * The export engine ships as product-only (EXP epic). This enum generalises
 * it across the five entity types the redesigned wizard offers (EXR spec §2,
 * decision D2):
 *
 *   - {@see self::Product}          built-in Product catalog (EAV objects).
 *   - {@see self::CustomModule}     a user-defined ObjectType (is_built_in=false);
 *                                   requires `object_type_id`.
 *   - {@see self::ModuleSchema}     ObjectType definitions / configuration.
 *   - {@see self::AttributesGroups} attribute dictionary + groups.
 *   - {@see self::Categories}       category tree.
 *
 * `Product` and `CustomModule` are catalog-object backed, so they support
 * filtering and target-scope selection. The three structural types export the
 * full configuration set and always run with `target_scope=all`.
 *
 * Runtime generation for each type is delivered incrementally: EXR-05 wires
 * the `custom_module` pipeline, EXR-06 the structural builders. Until a type's
 * builder exists, {@see self::isExecutable()} reports it as not-yet-runnable so
 * the API rejects execution with a clear message instead of failing mid-run.
 */
enum ExportEntityType: string
{
    case Product = 'product';
    case CustomModule = 'custom_module';
    case ModuleSchema = 'module_schema';
    case AttributesGroups = 'attributes_groups';
    case Categories = 'categories';

    /**
     * `custom_module` targets a user-defined ObjectType and therefore carries
     * an `object_type_id`. Every other type forbids it (the built-in Product is
     * reached through {@see self::Product}, not by pointing `custom_module` at
     * the built-in ObjectType).
     */
    public function requiresObjectType(): bool
    {
        return self::CustomModule === $this;
    }

    /**
     * Filtering / target-scope selection is only meaningful for entity types
     * backed by catalog objects. Structural types always export the full set.
     */
    public function supportsScopeAndFilter(): bool
    {
        return self::Product === $this || self::CustomModule === $this;
    }

    /**
     * Structural types export system configuration rather than EAV data and are
     * forced to `target_scope=all`.
     */
    public function isStructural(): bool
    {
        return !$this->supportsScopeAndFilter();
    }

    /**
     * Whether the export engine can currently generate this type.
     *
     * All five types are runnable as of EXR-06: product + custom_module share
     * the catalog-object pipeline (EXR-04/05); module_schema, attributes_groups
     * and categories run through the structural builders (EXR-06). The flag is
     * kept as the seam callers gate on, in case a future type ships its model
     * ahead of its generator.
     */
    public function isExecutable(): bool
    {
        return true;
    }
}
