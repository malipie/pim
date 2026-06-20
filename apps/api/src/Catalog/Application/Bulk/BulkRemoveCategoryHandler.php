<?php

declare(strict_types=1);

namespace App\Catalog\Application\Bulk;

use App\Catalog\Application\BulkContext;
use App\Catalog\Application\Reindex\BulkReindexQueueInterface;
use App\Catalog\Domain\Entity\BulkLog;
use App\Catalog\Domain\Entity\BulkSession;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectCategory;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectCategoryRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * VIEW-14 (#546) — `remove_category` bulk action.
 *
 * Removes the supplied category ids from each target product. Skips
 * pairs that are not currently assigned (warning log). Shared lifecycle:
 * {@see AbstractBulkHandler}.
 */
final class BulkRemoveCategoryHandler extends AbstractBulkHandler
{
    /** @var list<string> */
    private array $categoryIds = [];

    public function __construct(
        CatalogObjectRepositoryInterface $catalogObjects,
        private readonly ObjectCategoryRepositoryInterface $objectCategories,
        EntityManagerInterface $em,
        BulkContext $bulkContext,
        BulkReindexQueueInterface $reindexQueue,
    ) {
        parent::__construct($catalogObjects, $em, $bulkContext, $reindexQueue);
    }

    /**
     * @param list<string> $categoryIds
     *
     * @return array{success: int, skipped: int, error: int}
     */
    public function handle(BulkSession $session, array $categoryIds): array
    {
        $this->categoryIds = $categoryIds;

        return $this->runBatch($session);
    }

    protected function processObject(CatalogObject $object, BulkSession $session, BulkCounters $counters): void
    {
        $existing = $this->objectCategories->findByProduct($object);
        $existingIds = array_map(
            static fn (ObjectCategory $oc): string => $oc->getCategory()->getId()->toRfc4122(),
            $existing,
        );

        $removed = 0;
        foreach ($existing as $assignment) {
            if (\in_array($assignment->getCategory()->getId()->toRfc4122(), $this->categoryIds, true)) {
                $this->objectCategories->remove($assignment);
                ++$removed;
            }
        }

        if (0 === $removed) {
            ++$counters->skipped;
            $this->em->persist(new BulkLog(
                $session->getId(),
                $object->getId(),
                null,
                $existingIds,
                $existingIds,
                BulkLog::LEVEL_WARNING,
                'No matching category assignments',
            ));

            return;
        }

        $object->markTouchedByBulkSession($session->getId());
        $afterIds = array_values(array_diff($existingIds, $this->categoryIds));
        $this->em->persist(new BulkLog(
            $session->getId(),
            $object->getId(),
            null,
            $existingIds,
            $afterIds,
            BulkLog::LEVEL_INFO,
            null,
        ));
        ++$counters->success;
    }
}
