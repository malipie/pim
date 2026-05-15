<?php

declare(strict_types=1);

namespace App\Catalog\Application\Bulk;

use App\Catalog\Application\BulkContext;
use App\Catalog\Application\Reindex\BulkReindexQueueInterface;
use App\Catalog\Domain\Entity\BulkLog;
use App\Catalog\Domain\Entity\BulkSession;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Throwable;

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
 * ops, the recreate path is tracked separately.
 */
final class BulkDeleteHandler
{
    public const int CHUNK_SIZE = 200;

    public function __construct(
        private readonly CatalogObjectRepositoryInterface $catalogObjects,
        private readonly EntityManagerInterface $em,
        private readonly BulkContext $bulkContext,
        private readonly BulkReindexQueueInterface $reindexQueue,
    ) {
    }

    /**
     * @return array{success: int, skipped: int, error: int}
     */
    public function handle(BulkSession $session): array
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

            foreach ($session->getTargetObjectIds() as $targetId) {
                try {
                    $product = $this->catalogObjects->findById(Uuid::fromString($targetId));
                    if (!$product instanceof CatalogObject) {
                        ++$errors;
                        ++$chunkCounter;
                        continue;
                    }

                    $snapshot = [
                        'code' => $product->getCode(),
                        'kind' => $product->getKind()->value,
                        'attributes_indexed' => $product->getAttributesIndexed(),
                    ];

                    $this->em->persist(new BulkLog(
                        $session->getId(),
                        $product->getId(),
                        null,
                        $snapshot,
                        null,
                        BulkLog::LEVEL_INFO,
                        'deleted',
                    ));
                    $this->catalogObjects->remove($product);
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

            $this->reindexQueue->queueAllDeleted($session->getTargetObjectIds(), ObjectKind::Product);

            return ['success' => $success, 'skipped' => 0, 'error' => $errors];
        } finally {
            $this->bulkContext->setBulk(false);
        }
    }
}
