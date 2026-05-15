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
 * VIEW-14 (#546) — `move_category` bulk action.
 *
 * Full-replace assignment list. Wipes existing rows and re-inserts the
 * supplied target ids in a single transaction per product via the
 * repository's `replaceForProduct`. Used by the *„Przenieś kategorię"*
 * mockup flow when the operator picks a fresh taxonomy slot.
 */
final class BulkMoveCategoryHandler
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
        // Long bulk runs (2k+ products) routinely exceed PHP's
        // 30s HTTP timeout — without disabling it the handler
        // is killed mid-loop, the session stays incomplete, and
        // the operator sees 200 + zero rows touched.
        set_time_limit(0);

        $this->bulkContext->setBulk(true, $session->getId());
        try {
            $success = 0;
            $errors = 0;
            $chunkCounter = 0;
            $primaryId = [] === $categoryIds ? null : Uuid::fromString($categoryIds[0]);
            $targetUuids = array_map(static fn (string $id) => Uuid::fromString($id), $categoryIds);

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

                    $this->objectCategories->replaceForProduct($product, $targetUuids, $primaryId);
                    $product->markTouchedByBulkSession($session->getId());

                    $this->em->persist(new BulkLog(
                        $session->getId(),
                        $product->getId(),
                        null,
                        $existingIds,
                        $categoryIds,
                        BulkLog::LEVEL_INFO,
                        null,
                    ));
                    ++$success;
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

            // Reload BulkSession: per-chunk em->clear() detached the

            // local instance and its Tenant proxy. The final persist

            // below would otherwise raise EntityNotFoundException on

            // flush trying to resolve the stale proxy.

            $reloaded = $this->em->find(BulkSession::class, $session->getId());

            if ($reloaded instanceof BulkSession) {
                $session = $reloaded;
            }

            $session->complete($success, 0, $errors);
            $this->em->persist($session);
            $this->em->flush();

            $this->reindexQueue->queueAll($session->getTargetObjectIds());

            return ['success' => $success, 'skipped' => 0, 'error' => $errors];
        } finally {
            $this->bulkContext->setBulk(false);
        }
    }
}
