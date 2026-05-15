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
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * VIEW-13 (#545) — `append_value` bulk action (multiselect-safe).
 *
 * Appends a value to a list attribute. If the attribute slot is null or
 * scalar, it is promoted to `[old, new]` (dedup). If the value is
 * already present, the row is skipped (no-op, info log). Locked
 * attributes (VIEW-33 / PRD §8.3) skip with a warning entry.
 */
final class BulkAppendValueHandler
{
    public const int CHUNK_SIZE = 200;

    public function __construct(
        private readonly CatalogObjectRepositoryInterface $catalogObjects,
        private readonly EntityManagerInterface $em,
        private readonly BulkContext $bulkContext,
        private readonly AttributeLockReader $lockReader,
        private readonly BulkReindexQueueInterface $reindexQueue,
    ) {
    }

    /**
     * @return array{success: int, skipped: int, error: int}
     */
    public function handle(BulkSession $session, string $attrCode, mixed $value): array
    {
        // Long bulk runs (2k+ products) routinely exceed PHP's
        // 30s HTTP timeout — without disabling it the handler
        // is killed mid-loop, the session stays incomplete, and
        // the operator sees 200 + zero rows touched.
        set_time_limit(0);

        $this->bulkContext->setBulk(true, $session->getId());
        try {
            $success = 0;
            $skipped = 0;
            $errors = 0;
            $chunkCounter = 0;

            foreach ($session->getTargetObjectIds() as $targetId) {
                try {
                    $object = $this->catalogObjects->findById(Uuid::fromString($targetId));
                    if (!$object instanceof CatalogObject) {
                        ++$errors;
                        ++$chunkCounter;
                        continue;
                    }

                    if ($this->lockReader->isLocked($object, $attrCode)) {
                        ++$skipped;
                        $this->em->persist(new BulkLog(
                            $session->getId(),
                            $object->getId(),
                            null,
                            $object->getAttributesIndexed()[$attrCode] ?? null,
                            $object->getAttributesIndexed()[$attrCode] ?? null,
                            BulkLog::LEVEL_WARNING,
                            'Attribute locked',
                        ));
                        ++$chunkCounter;
                        if ($chunkCounter >= self::CHUNK_SIZE) {
                            $this->em->flush();
                            $this->em->clear();
                            $chunkCounter = 0;
                        }
                        continue;
                    }

                    $indexed = $object->getAttributesIndexed();
                    $oldValue = $indexed[$attrCode] ?? null;
                    $list = match (true) {
                        \is_array($oldValue) => $oldValue,
                        null === $oldValue => [],
                        default => [$oldValue],
                    };

                    if (\in_array($value, $list, true)) {
                        ++$skipped;
                        $this->em->persist(new BulkLog(
                            $session->getId(),
                            $object->getId(),
                            null,
                            $oldValue,
                            $oldValue,
                            BulkLog::LEVEL_WARNING,
                            'Value already present',
                        ));
                    } else {
                        $list[] = $value;
                        $indexed[$attrCode] = $list;
                        $object->updateAttributeIndex($indexed);
                        $object->markTouchedByBulkSession($session->getId());
                        $this->em->persist(new BulkLog(
                            $session->getId(),
                            $object->getId(),
                            null,
                            $oldValue,
                            $list,
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

            // Reload BulkSession: per-chunk em->clear() detached the

            // local instance and its Tenant proxy. The final persist

            // below would otherwise raise EntityNotFoundException on

            // flush trying to resolve the stale proxy.

            $reloaded = $this->em->find(BulkSession::class, $session->getId());

            if ($reloaded instanceof BulkSession) {
                $session = $reloaded;
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
