<?php

declare(strict_types=1);

namespace App\Export\Domain\Enum;

/**
 * Target scope for the export run (PRD §13.1, BaseLinker-style).
 *
 * `selected` — explicit list of object ids (from cross-page selection state
 *   in the products list `BulkActionsToolbar`).
 * `filter` — apply a filter snapshot, export everything matching.
 * `all` — every product in the tenant (subject to soft 100k / hard 500k
 *   caps in PRD §11.2).
 */
enum ExportTargetScope: string
{
    case Selected = 'selected';
    case Filter = 'filter';
    case All = 'all';
}
