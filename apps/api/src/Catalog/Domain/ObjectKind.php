<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

/**
 * Catalog of ObjectType kinds — the stable contract that drives polymorphic
 * behaviour on `objects` (in #34) and adapts UI / API surface per kind.
 *
 * Per ADR-009: `Product`, `Category`, `Asset` are predefined as built-in
 * fixtures (`is_built_in=true` on `ObjectType`) and ship with dedicated
 * sugar paths in the API (`/api/products`, `/api/categories`,
 * `/api/assets`). `Custom` is the escape hatch for tenant-defined kinds
 * (Customer, Supplier, PriceList in phase 2/3) — gated behind a feature
 * flag in `ObjectTypeService` so MVP cannot accidentally create one.
 *
 * `Brand` was previously seeded as a 4th built-in kind (MOD-10 #902) but
 * reverted by ADR-014 — brand is now a tenant decision (select attribute,
 * custom ObjectType, or external integration) rather than a platform-owned
 * concept. UX-01 finishes the cleanup by removing the enum case.
 *
 * `isBuiltIn()` is the semantic flag used by the service layer + tests to
 * tell "this kind is owned by the platform, not the tenant" apart from
 * the `is_built_in` boolean column on individual ObjectType rows. The
 * column is per-row (one tenant could in theory create a custom row of
 * kind=`product`); the enum bit is per-kind.
 */
enum ObjectKind: string
{
    case Product = 'product';
    case Category = 'category';
    case Asset = 'asset';
    case Custom = 'custom';

    public function isBuiltIn(): bool
    {
        return self::Custom !== $this;
    }
}
