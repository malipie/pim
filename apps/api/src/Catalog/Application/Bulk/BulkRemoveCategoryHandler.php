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
use Throwable;

/**
 * VIEW-14 (#546) — `remove_category` bulk action.
 *
 * Removes the supplied category ids from each target product. Skips
 * pairs that are not currently assigned (warning log).
 */
final class BulkRemoveCategoryHandler
{
    public const int CHUNK_SIZE = 200;

    public function __construct(
        private readonly CatalogObjectRepositoryInterface $catalogObjects,
        private readonly ObjectCategoryRepositoryInterface $objectCategories,
        private readonly EntityManagerInterface $em,
        private readonly BulkContext $bulkContext,
        private readonly BulkReindexQueueInterface $reindexQueue,
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

                    $removed = 0;
                    foreach ($existing as $assignment) {
                        if (\in_array($assignment->getCategory()->getId()->toRfc4122(), $categoryIds, true)) {
                            $this->objectCategories->remove($assignment);
                            ++$removed;
                        }
                    }

                    if (0 === $removed) {
                        ++$skipped;
                        $this->em->persist(new BulkLog(
                            $session->getId(),
                            $product->getId(),
                            null,
                            $existingIds,
                            $existingIds,
                            BulkLog::LEVEL_WARNING,
                            'No matching category assignments',
                        ));
                    } else {
                        $product->markTouchedByBulkSession($session->getId());
                        $afterIds = array_values(array_diff($existingIds, $categoryIds));
                        $this->em->persist(new BulkLog(
                            $session->getId(),
                            $product->getId(),
                            null,
                            $existingIds,
                            $afterIds,
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
