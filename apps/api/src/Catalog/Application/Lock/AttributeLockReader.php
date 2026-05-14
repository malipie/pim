<?php

declare(strict_types=1);

namespace App\Catalog\Application\Lock;

use App\Catalog\Domain\Entity\CatalogObject;

/**
 * VIEW-18 (#549) — read locked attribute codes for a catalog object.
 *
 * Storage: `attributes_indexed['__locks']` JSONB array of attribute
 * codes. The double-underscore prefix marks it as a meta-slot so the
 * value never collides with a real attribute called `locks`. Avoids a
 * dedicated migration in MVP — the existing GIN index covers the slot.
 */
final class AttributeLockReader
{
    public const string LOCKS_META_KEY = '__locks';

    /**
     * @return list<string>
     */
    public function getLockedCodes(CatalogObject $object): array
    {
        $indexed = $object->getAttributesIndexed();
        $raw = $indexed[self::LOCKS_META_KEY] ?? null;
        if (!\is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $code) {
            if (\is_string($code) && '' !== $code) {
                $out[] = $code;
            }
        }

        return $out;
    }

    public function isLocked(CatalogObject $object, string $attrCode): bool
    {
        return \in_array($attrCode, $this->getLockedCodes($object), true);
    }
}
