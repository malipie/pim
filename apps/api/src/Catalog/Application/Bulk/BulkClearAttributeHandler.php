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
 * VIEW-13 (#545) — `clear_attribute` bulk action.
 *
 * Removes the attribute slot from `attributes_indexed` (unset). Used
 * for resetting a field across N products. BulkLog records `old_value`
 * for the 24h rollback path. Locked attributes (VIEW-33 / PRD §8.3)
 * skip with a warning entry.
 */
final class BulkClearAttributeHandler
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
     * @return array{success: int, skipped: int, error: int}
     */
    public function handle(BulkSession $session, string $attrCode): array
    {
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
                    unset($indexed[$attrCode]);
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
