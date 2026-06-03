<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Domain\Entity\CatalogObject;
use DateTimeInterface;

/**
 * #1207 — read-only overlay that injects the system attributes
 * (`created_at`, `updated_at`, `created_by`, `updated_by`) into the
 * `attributesIndexed` map returned by the item GET providers.
 *
 * These attributes are never stored as ObjectValue rows, so without this
 * overlay they render as "—" on the detail page. The values are derived:
 * timestamps from the entity, actor e-mails from the blameable columns.
 *
 * The overlay clones the object and uses the side-effect-free
 * {@see CatalogObject::overlayAttributesIndexedForRead()} so it never touches
 * `updatedAt`, records a domain event, or risks persistence on a GET — the
 * same non-persisting contract the locale/channel overlay relies on.
 */
final readonly class SystemAttributeReadOverlay
{
    public function apply(CatalogObject $object): CatalogObject
    {
        $indexed = $object->getAttributesIndexed();

        // Envelope shape `{value: …}` matches the JSONB contract the admin's
        // `unwrapAttributesIndexed` lifts; created_at/updated_at render as
        // datetime, created_by/updated_by as a plain string (reference→user).
        $indexed['created_at'] = ['value' => $object->getCreatedAt()->format(DateTimeInterface::ATOM)];
        $indexed['updated_at'] = ['value' => $object->getUpdatedAt()->format(DateTimeInterface::ATOM)];

        $createdBy = $object->getCreatedBy();
        if (null !== $createdBy) {
            $indexed['created_by'] = ['value' => $createdBy];
        }

        $updatedBy = $object->getUpdatedBy();
        if (null !== $updatedBy) {
            $indexed['updated_by'] = ['value' => $updatedBy];
        }

        $copy = clone $object;
        $copy->overlayAttributesIndexedForRead($indexed);

        return $copy;
    }
}
