<?php

declare(strict_types=1);

namespace App\Catalog\Application\Bulk;

use App\Catalog\Application\BulkContext;
use App\Catalog\Application\Lock\AttributeLockReader;
use App\Catalog\Domain\Entity\BulkLog;
use App\Catalog\Domain\Entity\BulkSession;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * VIEW-12 (#543) — handler for `set_attribute` bulk action.
 *
 * Synchronous in MVP (<100 SKU per click). Async via Symfony Messenger
 * follows in VIEW-12.1. Chunk size N=200 + `EntityManager::clear()`
 * per chunk to honour the FrankenPHP worker memory rule (CLAUDE.md
 * §3.10).
 *
 * Returns a result summary the controller serialises to the wizard's
 * Step 3 stat grid. `BulkSession` carries the rollback recipe via the
 * accompanying `bulk_logs` rows.
 */
final class BulkSetAttributeHandler
{
    public const int CHUNK_SIZE = 200;

    public function __construct(
        private readonly CatalogObjectRepositoryInterface $catalogObjects,
        private readonly EntityManagerInterface $em,
        private readonly BulkContext $bulkContext,
        private readonly AttributeLockReader $lockReader,
    ) {
    }

    /**
     * Apply a `set_attribute` action to every target id, writing
     * BulkLog rows for the rollback path. Locked attributes (VIEW-18)
     * skip with a warning entry.
     *
     * @return array{success: int, skipped: int, error: int}
     */
    public function handle(BulkSession $session, string $attrCode, mixed $newValue): array
    {
        $this->bulkContext->setBulk(true);
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
                        $this->em->persist(new BulkLog(
                            $session->getId(),
                            Uuid::fromString($targetId),
                            null,
                            null,
                            null,
                            BulkLog::LEVEL_ERROR,
                            'Object not found',
                        ));
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

                    $oldValue = $object->getAttributesIndexed()[$attrCode] ?? null;

                    // Set in attributesIndexed (denormalised JSONB) — the
                    // canonical write path lives in object_values; for the
                    // MVP slice this is a thin wrapper, full write through
                    // ObjectAttributesUpserter lands in VIEW-13.
                    $indexed = $object->getAttributesIndexed();
                    $indexed[$attrCode] = $newValue;
                    $object->updateAttributeIndex($indexed);

                    $this->em->persist(new BulkLog(
                        $session->getId(),
                        $object->getId(),
                        null,
                        $oldValue,
                        $newValue,
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

            $session->complete($success, $skipped, $errors);
            $this->em->persist($session);
            $this->em->flush();

            return ['success' => $success, 'skipped' => $skipped, 'error' => $errors];
        } finally {
            $this->bulkContext->setBulk(false);
        }
    }
}
