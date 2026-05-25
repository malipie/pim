<?php

declare(strict_types=1);

namespace App\Identity\Contracts\Policy;

use Symfony\Component\Uid\Uuid;

/**
 * ULV-04b (#986) — cross-context contract for the 3-state per-attribute
 * permission resolution (PRD §3.5).
 *
 * The full {@see \App\Identity\Application\Policy\AttributePermissionPolicy}
 * resolver lives in `Identity_Internals` and cannot be imported by
 * Catalog (Deptrac). This contract exposes the minimum surface other
 * bundles need — answering "can the **current** user view / edit this
 * attribute id" — so the universal list schema (ULV-03) and any future
 * field-level filtering can gate columns and serialized values without
 * leaking the policy chain.
 *
 * The current user / tenant comes from the Symfony security token via
 * the adapter; callers do not pass the user explicitly. That keeps the
 * surface narrow and the contract testable with a simple fake.
 *
 * Implementations must return `false` for anonymous principals (no
 * authenticated user → no view → restricted column).
 */
interface AttributePermissionReader
{
    public function canViewAttribute(Uuid $attributeId): bool;

    public function canEditAttribute(Uuid $attributeId): bool;
}
