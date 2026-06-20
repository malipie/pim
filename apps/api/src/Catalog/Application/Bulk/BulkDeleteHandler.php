<?php

declare(strict_types=1);

namespace App\Catalog\Application\Bulk;

use App\Catalog\Domain\Entity\BulkLog;
use App\Catalog\Domain\Entity\BulkSession;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\ObjectKind;

/**
 * VIEW-16 (#548) — `delete` bulk action.
 *
 * Cascade deletes the row from `objects` (Doctrine cascade wipes
 * ObjectValues + ObjectCategory rows). Asset records in DAM stay
 * untouched per PRD §7 — only PIM-side metadata is removed.
 *
 * BulkLog `old_value` snapshots the product code + a flag indicating
 * deletion so rollback can recreate the row in a follow-up handler;
 * present executor only flags the session as containing destructive
 * ops, the recreate path is tracked separately. Shared lifecycle lives
 * in {@see AbstractBulkHandler}; this handler overrides {@see reindex()}
 * to remove the touched documents from Meilisearch.
 */
final class BulkDeleteHandler extends AbstractBulkHandler
{
    /**
     * @return array{success: int, skipped: int, error: int}
     */
    public function handle(BulkSession $session): array
    {
        return $this->runBatch($session);
    }

    protected function processObject(CatalogObject $object, BulkSession $session, BulkCounters $counters): void
    {
        $snapshot = [
            'code' => $object->getCode(),
            'kind' => $object->getKind()->value,
            'attributes_indexed' => $object->getAttributesIndexed(),
        ];

        $this->em->persist(new BulkLog(
            $session->getId(),
            $object->getId(),
            null,
            $snapshot,
            null,
            BulkLog::LEVEL_INFO,
            'deleted',
        ));
        $this->catalogObjects->remove($object);
        ++$counters->success;
    }

    protected function reindex(BulkSession $session): void
    {
        $this->reindexQueue->queueAllDeleted($session->getTargetObjectIds(), ObjectKind::Product);
    }
}
