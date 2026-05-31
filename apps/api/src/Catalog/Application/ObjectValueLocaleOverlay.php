<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Repository\ObjectValueRepositoryInterface;
use App\Shared\Domain\Tenant;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Overlays per-locale {@see \App\Catalog\Domain\Entity\ObjectValue} rows on
 * top of an object's global `attributes_indexed` cache so a locale-scoped
 * read (`GET /api/products/{id}?locale=en`) returns the localized reading
 * (#1146 / #1148).
 *
 * Contract:
 *   - The default locale is stored as the GLOBAL row (`locale=null`), so a
 *     read in the tenant's primary locale needs no overlay — the cache
 *     already holds those values.
 *   - For any other locale, each localizable attribute that has a
 *     `locale=<code>` row is swapped in; attributes without a per-locale
 *     row keep their global value (fallback — surfaces legacy data and
 *     shared/non-localizable values unchanged).
 *
 * Returns a detached CLONE with the overlaid index — never the managed
 * entity. Mutating the managed instance would leak the locale reading onto
 * the identity map and corrupt any later read of the same object in the
 * same EntityManager (e.g. a subsequent bare GET). The clone shares the
 * entity's relation references (objectType, tenant) so serialization + the
 * READ voter keep working; only the scalar `attributes_indexed` array
 * (copied by value on clone) is overlaid.
 */
final readonly class ObjectValueLocaleOverlay
{
    public function __construct(
        private ObjectValueRepositoryInterface $values,
    ) {
    }

    public function apply(CatalogObject $object, string $locale): CatalogObject
    {
        $tenant = $object->getTenant();
        if (!$tenant instanceof Tenant || !$tenant->isLocaleEnabled($locale)) {
            throw new UnprocessableEntityHttpException(\sprintf(
                'Locale "%s" is not enabled for this tenant.',
                $locale,
            ));
        }

        // Primary locale === global row: the cache already holds it.
        if ($locale === $tenant->getPrimaryLocale()) {
            return $object;
        }

        $indexed = $object->getAttributesIndexed();
        foreach ($this->values->findByObject($object) as $value) {
            if ($locale !== $value->getLocale() || null !== $value->getChannelId()) {
                continue;
            }
            $indexed[$value->getAttribute()->getCode()] = $value->getValue();
        }

        $copy = clone $object;
        $copy->updateAttributeIndex($indexed);

        return $copy;
    }
}
