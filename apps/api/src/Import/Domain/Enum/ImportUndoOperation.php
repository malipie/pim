<?php

declare(strict_types=1);

namespace App\Import\Domain\Enum;

/**
 * IMP2-2.4 — kind of reversible change recorded in {@see \App\Import\Domain\Entity\ImportUndoLog}.
 */
enum ImportUndoOperation: string
{
    /** An existing object_value was overwritten — payload carries the before-envelope. */
    case ValueOverwritten = 'value_overwritten';

    /** A new object_value was added to a pre-existing object — rollback deletes it. */
    case ValueCreated = 'value_created';

    /** status / enabled / parent_id / variant_axes changed on a pre-existing object. */
    case ObjectFieldChanged = 'object_field_changed';

    /** Categories were replaced on a pre-existing object — payload carries the prior set. */
    case CategorySet = 'category_set';

    /** Cross-object relations were added to a pre-existing object — rollback removes them. */
    case RelationCreated = 'relation_created';
}
