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
use Symfony\Component\Uid\Uuid;

/**
 * VIEW-14 (#546) — `add_category` bulk action.
 *
 * Adds the supplied category ids to each target product. Skips a
 * (product, category) pair when the assignment already exists (warning
 * log). BulkLog `old_value` snapshots the prior assignment id list to
 * support the 24h rollback path via `replaceForProduct`. Shared
 * lifecycle: {@see AbstractBulkHandler}.
 */
final class BulkAddCategoryHandler extends AbstractBulkHandler
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

        $added = 0;
        foreach ($this->categoryIds as $catId) {
            if (\in_array($catId, $existingIds, true)) {
                continue;
            }
            $category = $this->catalogObjects->findById(Uuid::fromString($catId));
            if (!$category instanceof CatalogObject) {
                continue;
            }
            $this->objectCategories->save(new ObjectCategory($object, $category));
            ++$added;
        }

        if (0 === $added) {
            ++$counters->skipped;
            $this->em->persist(new BulkLog(
                $session->getId(),
                $object->getId(),
                null,
                $existingIds,
                $existingIds,
                BulkLog::LEVEL_WARNING,
                'All categories already assigned',
            ));

            return;
        }

        $object->markTouchedByBulkSession($session->getId());
        $this->em->persist(new BulkLog(
            $session->getId(),
            $object->getId(),
            null,
            $existingIds,
            array_values(array_unique([...$existingIds, ...$this->categoryIds])),
            BulkLog::LEVEL_INFO,
            null,
        ));
        ++$counters->success;
    }
}
