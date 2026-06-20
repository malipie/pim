<?php

declare(strict_types=1);

namespace App\Catalog\Application\Bulk;

use App\Catalog\Application\BulkContext;
use App\Catalog\Application\Lock\AttributeLockReader;
use App\Catalog\Application\Reindex\BulkReindexQueueInterface;
use App\Catalog\Domain\Entity\BulkLog;
use App\Catalog\Domain\Entity\BulkSession;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * VIEW-13 (#545) — `remove_value` bulk action (multiselect-safe).
 *
 * Removes a value from a list attribute. If the slot is scalar matching
 * the value, it becomes null (unset). If the value is not present, the
 * row is skipped (no-op, warning log). Locked attributes (VIEW-33 /
 * PRD §8.3) skip with a warning entry. Shared lifecycle:
 * {@see AbstractBulkHandler}.
 */
final class BulkRemoveValueHandler extends AbstractBulkHandler
{
    private string $attrCode = '';
    private mixed $value = null;

    public function __construct(
        CatalogObjectRepositoryInterface $catalogObjects,
        EntityManagerInterface $em,
        BulkContext $bulkContext,
        private readonly AttributeLockReader $lockReader,
        BulkReindexQueueInterface $reindexQueue,
    ) {
        parent::__construct($catalogObjects, $em, $bulkContext, $reindexQueue);
    }

    /**
     * @return array{success: int, skipped: int, error: int}
     */
    public function handle(BulkSession $session, string $attrCode, mixed $value): array
    {
        $this->attrCode = $attrCode;
        $this->value = $value;

        return $this->runBatch($session);
    }

    protected function processObject(CatalogObject $object, BulkSession $session, BulkCounters $counters): void
    {
        if ($this->lockReader->isLocked($object, $this->attrCode)) {
            ++$counters->skipped;
            $this->em->persist(new BulkLog(
                $session->getId(),
                $object->getId(),
                null,
                $object->getAttributesIndexed()[$this->attrCode] ?? null,
                $object->getAttributesIndexed()[$this->attrCode] ?? null,
                BulkLog::LEVEL_WARNING,
                'Attribute locked',
            ));

            return;
        }

        $indexed = $object->getAttributesIndexed();
        $oldValue = $indexed[$this->attrCode] ?? null;

        if (\is_array($oldValue)) {
            $idx = array_search($this->value, $oldValue, true);
            if (false === $idx) {
                ++$counters->skipped;
                $this->em->persist(new BulkLog(
                    $session->getId(),
                    $object->getId(),
                    null,
                    $oldValue,
                    $oldValue,
                    BulkLog::LEVEL_WARNING,
                    'Value not present',
                ));

                return;
            }

            $newList = array_values(array_filter(
                $oldValue,
                fn ($v) => $v !== $this->value,
            ));
            $indexed[$this->attrCode] = $newList;
            $object->updateAttributeIndex($indexed);
            $object->markTouchedByBulkSession($session->getId());
            $this->em->persist(new BulkLog(
                $session->getId(),
                $object->getId(),
                null,
                $oldValue,
                $newList,
                BulkLog::LEVEL_INFO,
                null,
            ));
            ++$counters->success;

            return;
        }

        if ($oldValue === $this->value) {
            unset($indexed[$this->attrCode]);
            $object->updateAttributeIndex($indexed);
            $object->markTouchedByBulkSession($session->getId());
            $this->em->persist(new BulkLog(
                $session->getId(),
                $object->getId(),
                null,
                $oldValue,
                null,
                BulkLog::LEVEL_INFO,
                null,
            ));
            ++$counters->success;

            return;
        }

        ++$counters->skipped;
        $this->em->persist(new BulkLog(
            $session->getId(),
            $object->getId(),
            null,
            $oldValue,
            $oldValue,
            BulkLog::LEVEL_WARNING,
            'Value not present',
        ));
    }
}
