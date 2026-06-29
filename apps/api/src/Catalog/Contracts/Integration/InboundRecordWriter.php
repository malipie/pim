<?php

declare(strict_types=1);

namespace App\Catalog\Contracts\Integration;

use Symfony\Component\Uid\Uuid;

/**
 * Cross-BC seam for the API Configurator inbound sync (APIC-P3-04, ADR-0022).
 *
 * Lets the Integration context upsert a remote record into the catalog through
 * the proven write core ({@see \App\Catalog\Application\BatchValueWriter},
 * provenance fixed to `integration`) without importing any Catalog Application
 * or Domain type — ADR-0022 keeps cross-BC coupling at the Contracts level.
 *
 * The implementation resolves the target object by `matchAttributeCode = matchValue`
 * within the ObjectType (tenant-scoped), creates it when absent, writes the
 * mapped attribute values and rebuilds the denormalised index. It is the
 * consumer-sync analogue of the IMP2 import upsert.
 */
interface InboundRecordWriter
{
    /**
     * Upserts one remote record. `attributeValues` is `attributeCode => scalar`
     * (the field mapping already resolved the PIM targets). The match attribute
     * value is written on create so the new object carries its identity.
     *
     * @param array<string, scalar> $attributeValues
     */
    public function upsert(
        Uuid $objectTypeId,
        string $matchAttributeCode,
        string $matchValue,
        array $attributeValues,
    ): InboundUpsertResult;
}
