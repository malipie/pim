<?php

declare(strict_types=1);

namespace App\Catalog\Application\Bulk;

use App\Catalog\Application\BulkContext;
use App\Catalog\Domain\Entity\BulkLog;
use App\Catalog\Domain\Entity\BulkSession;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectCategory;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectCategoryRepositoryInterface;
use App\Search\Application\BulkReindexQueue;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * VIEW-14 (#546) — `add_category` bulk action.
 *
 * Adds the supplied category ids to each target product. Skips a
 * (product, category) pair when the assignment already exists (warning
 * log). BulkLog `old_value` snapshots the prior assignment id list to
 * support the 24h rollback path via `replaceForProduct`.
 */
final class BulkAddCategoryHandler
{
    public const int CHUNK_SIZE = 200;

    public function __construct(
        private readonly CatalogObjectRepositoryInterface $catalogObjects,
        private readonly ObjectCategoryRepositoryInterface $objectCategories,
        private readonly EntityManagerInterface $em,
        private readonly BulkContext $bulkContext,
        private readonly BulkReindexQueue $reindexQueue,
    ) {
    }

    /**
     * @param list<string> $categoryIds
     *
     * @return array{success: int, skipped: int, error: int}
     */
    public function handle(BulkSession $session, array $categoryIds): array
    {
        $this->bulkContext->setBulk(true, $session->getId());
        try {
            $success = 0;
            $skipped = 0;
            $errors = 0;
            $chunkCounter = 0;

            foreach ($session->getTargetObjectIds() as $targetId) {
                try {
                    $product = $this->catalogObjects->findById(Uuid::fromString($targetId));
                    if (!$product instanceof CatalogObject) {
                        ++$errors;
                        ++$chunkCounter;
                        continue;
                    }

                    $existing = $this->objectCategories->findByProduct($product);
                    $existingIds = array_map(
                        static fn (ObjectCategory $oc): string => $oc->getCategory()->getId()->toRfc4122(),
                        $existing,
                    );

                    $added = 0;
                    foreach ($categoryIds as $catId) {
                        if (\in_array($catId, $existingIds, true)) {
                            continue;
                        }
                        $category = $this->catalogObjects->findById(Uuid::fromString($catId));
                        if (!$category instanceof CatalogObject) {
                            continue;
                        }
                        $this->objectCategories->save(new ObjectCategory($product, $category));
                        ++$added;
                    }

                    if (0 === $added) {
                        ++$skipped;
                        $this->em->persist(new BulkLog(
                            $session->getId(),
                            $product->getId(),
                            null,
                            $existingIds,
                            $existingIds,
                            BulkLog::LEVEL_WARNING,
                            'All categories already assigned',
                        ));
                    } else {
                        $product->markTouchedByBulkSession($session->getId());
                        $this->em->persist(new BulkLog(
                            $session->getId(),
                            $product->getId(),
                            null,
                            $existingIds,
                            array_values(array_unique([...$existingIds, ...$categoryIds])),
                            BulkLog::LEVEL_INFO,
                            null,
                        ));
                        ++$success;
                    }
                } catch (Throwable $e) {
                    ++$errors;
                    $this->em->persist(new BulkLog(
                        $session->getId(),
                        Uuid::fromString($targetId),
                        null,
                        null,
                        null,
                        BulkLog::LEVEL_ERROR,
                        $e->getMessage(),
                    ));
                }

                ++$chunkCounter;
                if ($chunkCounter >= self::CHUNK_SIZE) {
                    $this->em->flush();
                    $this->em->clear();
                    $chunkCounter = 0;
                }
            }

            if ($chunkCounter > 0) {
                $this->em->flush();
            }

            $session->complete($success, $skipped, $errors);
            $this->em->persist($session);
            $this->em->flush();

            $this->reindexQueue->queueAll($session->getTargetObjectIds());

            return ['success' => $success, 'skipped' => $skipped, 'error' => $errors];
        } finally {
            $this->bulkContext->setBulk(false);
        }
    }
}
