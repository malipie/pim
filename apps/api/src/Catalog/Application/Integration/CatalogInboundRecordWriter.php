<?php

declare(strict_types=1);

namespace App\Catalog\Application\Integration;

use App\Catalog\Application\BatchValueWriter;
use App\Catalog\Contracts\Integration\InboundRecordWriter;
use App\Catalog\Contracts\Integration\InboundUpsertResult;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Provenance;
use App\Catalog\Domain\Repository\AttributeRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Shared\Application\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Catalog-side implementation of the inbound-sync write seam (APIC-P3-04).
 *
 * Reuses {@see BatchValueWriter} (the IMP2 write core) with `Provenance::Integration`,
 * resolving the target object by a match attribute value within the ObjectType
 * (the same SQL pattern as the import `ObjectResolver`, replicated here because
 * that service lives in the Import context and Catalog must not depend on it)
 * and creating it when absent. It persists into the unit of work but does not
 * flush — the sync runner flushes per batch, which fires the synchronous
 * `AttributesIndexedSyncListener` to refresh the denormalised index (the
 * single-edit path; the bulk BulkContext+async path is reserved for large runs).
 */
final readonly class CatalogInboundRecordWriter implements InboundRecordWriter
{
    public function __construct(
        private EntityManagerInterface $em,
        private ObjectTypeRepositoryInterface $objectTypes,
        private AttributeRepositoryInterface $attributes,
        private BatchValueWriter $valueWriter,
        private TenantContext $tenantContext,
    ) {
    }

    public function upsert(
        Uuid $objectTypeId,
        string $matchAttributeCode,
        string $matchValue,
        array $attributeValues,
    ): InboundUpsertResult {
        $tenant = $this->tenantContext->get();
        if (null === $tenant) {
            return InboundUpsertResult::failed('No active tenant for inbound write.');
        }

        $matchValue = trim($matchValue);
        if ('' === $matchValue) {
            return InboundUpsertResult::failed('Empty match value; cannot resolve a record.');
        }

        $objectType = $this->objectTypes->findById($objectTypeId);
        if (null === $objectType) {
            return InboundUpsertResult::failed(\sprintf('ObjectType "%s" was not found.', $objectTypeId->toRfc4122()));
        }

        /** @var array<string, Attribute> $attrs */
        $attrs = [];
        foreach (array_unique([$matchAttributeCode, ...array_keys($attributeValues)]) as $code) {
            $attribute = $this->attributes->findByCode($code, $tenant);
            if (null !== $attribute) {
                $attrs[$code] = $attribute;
            }
        }

        if (!isset($attrs[$matchAttributeCode])) {
            return InboundUpsertResult::failed(\sprintf('Match attribute "%s" was not found.', $matchAttributeCode));
        }

        $existingId = $this->resolveObjectId(
            $objectTypeId->toRfc4122(),
            $tenant->getId()->toRfc4122(),
            $matchAttributeCode,
            $matchValue,
        );

        $isNew = null === $existingId;
        $object = $isNew
            ? new CatalogObject($objectType, $matchValue)
            : $this->em->find(CatalogObject::class, $existingId);

        if (!$object instanceof CatalogObject) {
            return InboundUpsertResult::failed('Resolved object vanished mid-write.');
        }

        if ($isNew) {
            $this->em->persist($object);
        }

        // On create, ensure the match value itself is written so the new object
        // carries its identity; mapped values override it if both are present.
        $envelopes = $attributeValues;
        if ($isNew && !\array_key_exists($matchAttributeCode, $envelopes)) {
            $envelopes[$matchAttributeCode] = $matchValue;
        }

        $writes = [];
        foreach ($envelopes as $code => $value) {
            if (!isset($attrs[$code])) {
                continue;
            }
            $writes[] = [
                'attribute' => $attrs[$code],
                'envelope' => ['value' => $value],
                'locale' => null,
                'channelId' => null,
            ];
        }

        $result = $this->valueWriter->writeMany($object, $writes, Provenance::Integration);

        $issues = array_map(static fn (array $issue): string => $issue['message'], $result['issues']);
        $action = $isNew ? 'created' : ($result['changed'] > 0 ? 'updated' : 'skipped');

        return new InboundUpsertResult($action, $object->getId()->toRfc4122(), $issues);
    }

    /**
     * Resolves the object id whose `matchAttributeCode` value equals `$matchValue`
     * within the ObjectType + tenant. Native SQL (no registered JSONB DQL) —
     * tenant scoping is therefore explicit, mirroring the import ObjectResolver.
     */
    private function resolveObjectId(
        string $objectTypeId,
        string $tenantId,
        string $matchAttributeCode,
        string $matchValue,
    ): ?string {
        $id = $this->em->getConnection()->fetchOne(
            <<<'SQL'
                SELECT o.id
                FROM objects o
                JOIN object_values ov ON ov.object_id = o.id
                JOIN attributes a ON a.id = ov.attribute_id
                WHERE a.code = :code
                  AND o.object_type_id = :objectTypeId
                  AND o.tenant_id = :tenantId
                  AND ov.value->>'value' = :matchValue
                LIMIT 1
                SQL,
            [
                'code' => $matchAttributeCode,
                'objectTypeId' => $objectTypeId,
                'tenantId' => $tenantId,
                'matchValue' => $matchValue,
            ],
        );

        return \is_string($id) ? $id : null;
    }
}
