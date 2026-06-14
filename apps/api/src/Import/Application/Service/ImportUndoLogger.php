<?php

declare(strict_types=1);

namespace App\Import\Application\Service;

use App\Catalog\Application\ValueWriteCore;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Repository\ObjectValueRepositoryInterface;
use App\Import\Domain\Entity\ImportSession;
use App\Import\Domain\Entity\ImportUndoLog;
use App\Import\Domain\Enum\ImportUndoOperation;
use App\Import\Domain\Repository\ImportUndoLogRepositoryInterface;
use App\Import\Domain\ValueObject\ResolvedImportValue;
use App\Shared\Domain\Tenant;
use Symfony\Component\Uid\Uuid;

/**
 * IMP2-2.4 — records the before-state of attribute-value writes the import makes
 * to PRE-EXISTING objects, so rollback v2 can restore overwritten values and
 * delete values it newly added. Created objects are NOT logged (rollback deletes
 * them wholesale by import_session_id — D11).
 *
 * Lives in the Import layer (the undo-log is an Import concern) and reuses the
 * Catalog {@see ValueWriteCore::routeScope()} so the captured scope is byte-for-
 * byte the one {@see \App\Catalog\Application\BatchValueWriter} writes — no
 * divergence, no wrong-cell restores. Persists without flushing; the handler's
 * flushAndClear commits the log with the chunk in one transaction.
 */
final class ImportUndoLogger
{
    /** @var array<string, \App\Catalog\Domain\Entity\ObjectValue> "objId|attrId|locale|channel" => existing row (primed per chunk) */
    private array $existingIndex = [];

    /** @var array<string, true> scope keys already captured this run (first-write-wins) */
    private array $capturedScopes = [];

    public function __construct(
        private readonly ImportUndoLogRepositoryInterface $undoLog,
        private readonly ObjectValueRepositoryInterface $objectValues,
        private readonly ValueWriteCore $valueWriteCore,
    ) {
    }

    /** Drop per-run state — call once at the start of an import run. */
    public function reset(): void
    {
        $this->existingIndex = [];
        $this->capturedScopes = [];
    }

    /**
     * Load the current ObjectValues of the chunk's UPDATE targets in one query
     * (mirrors BatchValueWriter::primeChunk) so per-row capture is in-memory.
     *
     * @param list<CatalogObject> $existingObjects the pre-existing match targets in this chunk
     */
    public function primeChunk(array $existingObjects): void
    {
        $this->existingIndex = [];
        if ([] === $existingObjects) {
            return;
        }
        $ids = array_map(static fn (CatalogObject $o): Uuid => $o->getId(), $existingObjects);
        // findByObjectIds returns values grouped by object id (RFC4122 => list).
        foreach ($this->objectValues->findByObjectIds($ids) as $values) {
            foreach ($values as $value) {
                $this->existingIndex[$this->key(
                    $value->getObject()->getId(),
                    $value->getAttribute()->getId(),
                    $value->getLocale(),
                    $value->getChannelId(),
                )] = $value;
            }
        }
    }

    /**
     * Capture the before-state for every value an UPDATE row is about to write
     * on a pre-existing object: an existing row → ValueOverwritten (before
     * envelope), a fresh scope → ValueCreated tombstone (rollback deletes it).
     *
     * @param list<ResolvedImportValue> $writes
     * @param array<string, Attribute>  $attributesByCode
     */
    public function captureValueWrites(
        ImportSession $session,
        CatalogObject $object,
        array $writes,
        array $attributesByCode,
        Tenant $tenant,
    ): void {
        if (!$session->isUndoLogEnabled()) {
            return;
        }
        $objectId = $object->getId();
        foreach ($writes as $write) {
            $attribute = $attributesByCode[$write->attributeCode] ?? null;
            if (!$attribute instanceof Attribute) {
                continue;
            }
            [$locale, $channelId] = $this->valueWriteCore->routeScope($attribute, $tenant, $write->locale, $write->channelId);
            $scopeKey = $this->key($objectId, $attribute->getId(), $locale, $channelId);
            if (isset($this->capturedScopes[$scopeKey])) {
                continue; // first-write-wins: keep the earliest before-state
            }
            $this->capturedScopes[$scopeKey] = true;

            $existing = $this->existingIndex[$scopeKey] ?? null;
            if (null !== $existing) {
                $payload = [
                    'value' => $existing->getValue(),
                    'provenance' => $existing->getProvenance()->value,
                    'provenance_meta' => $existing->getProvenanceMeta(),
                ];
                $operation = ImportUndoOperation::ValueOverwritten;
            } else {
                $payload = [];
                $operation = ImportUndoOperation::ValueCreated;
            }

            $this->undoLog->add(new ImportUndoLog(
                importSession: $session,
                objectId: $objectId,
                operation: $operation,
                payload: $payload,
                attributeCode: $attribute->getCode(),
                locale: $locale,
                channelId: $channelId,
            ));
        }
    }

    /**
     * IMP2-2.4 resume safety — rows ≤ the checkpoint are NOT re-written on a
     * resumed run (the prior run already logged their before-state), so mark
     * their scopes captured WITHOUT logging again. Otherwise a later row in the
     * same run touching the same scope would slip past first-write-wins and
     * append a duplicate, stale undo entry that corrupts the replay.
     *
     * @param list<ResolvedImportValue> $writes
     * @param array<string, Attribute>  $attributesByCode
     */
    public function markScopesCaptured(
        CatalogObject $object,
        array $writes,
        array $attributesByCode,
        Tenant $tenant,
    ): void {
        $objectId = $object->getId();
        foreach ($writes as $write) {
            $attribute = $attributesByCode[$write->attributeCode] ?? null;
            if (!$attribute instanceof Attribute) {
                continue;
            }
            [$locale, $channelId] = $this->valueWriteCore->routeScope($attribute, $tenant, $write->locale, $write->channelId);
            $this->capturedScopes[$this->key($objectId, $attribute->getId(), $locale, $channelId)] = true;
        }
    }

    private function key(Uuid $objectId, Uuid $attributeId, ?string $locale, ?Uuid $channelId): string
    {
        return $objectId->toRfc4122().'|'.$attributeId->toRfc4122().'|'.($locale ?? '').'|'.($channelId?->toRfc4122() ?? '');
    }
}
