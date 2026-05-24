<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

/**
 * Cardinality of a `relation`-typed attribute (ADR-014 / MOD-01).
 *
 * - `One`  — at most one target object linked from a given source via this
 *   attribute. UI surfaces a single-pick picker; backend enforces at
 *   write time (a second link replaces the first).
 * - `Many` — unbounded list of links, ordered by `object_relations.position`.
 *   UI surfaces an add/remove/reorder grid.
 *
 * NULL on the attribute means the attribute is not of type `relation` —
 * the column carries `relation_cardinality NULL` for non-relation rows,
 * and the entity property is `?RelationCardinality` accordingly.
 */
enum RelationCardinality: string
{
    case One = 'one';
    case Many = 'many';
}
