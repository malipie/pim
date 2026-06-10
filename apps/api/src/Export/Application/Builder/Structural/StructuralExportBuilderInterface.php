<?php

declare(strict_types=1);

namespace App\Export\Application\Builder\Structural;

use App\Export\Domain\Enum\ExportEntityType;
use App\Shared\Domain\Tenant;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * EXR-06 (#1382) — registry of builders for the structural export types.
 *
 * Structural exports (module_schema / attributes_groups / categories) emit the
 * system *configuration* rather than EAV data, so they do not flow through the
 * catalog-object pipeline ({@see \App\Export\Application\Sync\SyncExportRunner}
 * dispatches product/custom_module there). Each implementation yields a flat
 * table — ordered column keys + rows keyed by those columns — which the runner
 * streams into the existing CSV/XLSX writers.
 *
 * Implementations are autoconfigured into the `app.export.structural_builder`
 * tag and consumed by the runner via a tagged iterator.
 */
#[AutoconfigureTag('app.export.structural_builder')]
interface StructuralExportBuilderInterface
{
    public function supports(ExportEntityType $type): bool;

    /**
     * Ordered default column keys. Depends on the tenant because some types
     * fan out per active locale (e.g. `label.pl`, `label.en`).
     *
     * @return list<string>
     */
    public function columns(Tenant $tenant): array;

    /**
     * @return iterable<array<string, string>> rows keyed by column key
     */
    public function rows(Tenant $tenant): iterable;

    public function count(Tenant $tenant): int;
}
