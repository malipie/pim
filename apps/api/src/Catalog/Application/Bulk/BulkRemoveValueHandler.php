<?php

declare(strict_types=1);

namespace App\Catalog\Application\Bulk;

use App\Catalog\Application\BulkContext;
use App\Catalog\Domain\Entity\BulkLog;
use App\Catalog\Domain\Entity\BulkSession;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * VIEW-13 (#545) — `remove_value` bulk action (multiselect-safe).
 *
 * Removes a value from a list attribute. If the slot is scalar matching
 * the value, it becomes null (unset). If the value is not present, the
 * row is skipped (no-op, warning log).
 */
final class BulkRemoveValueHandler
{
    public const int CHUNK_SIZE = 200;

    public function __construct(
        private readonly CatalogObjectRepositoryInterface $catalogObjects,
        private readonly EntityManagerInterface $em,
        private readonly BulkContext $bulkContext,
    ) {
    }

    /**
     * @return array{success: int, skipped: int, error: int}
     */
    public function handle(BulkSession $session, string $attrCode, mixed $value): array
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
                        ++$chunkCounter;
                        continue;
                    }

                    $indexed = $object->getAttributesIndexed();
                    $oldValue = $indexed[$attrCode] ?? null;

                    if (\is_array($oldValue)) {
                        $idx = array_search($value, $oldValue, true);
                        if (false === $idx) {
                            ++$skipped;
                            $this->em->persist(new BulkLog(
                                $session->getId(),
                                $object->getId(),
                                null,
                                $oldValue,
                                $oldValue,
                                BulkLog::LEVEL_WARNING,
                                'Value not present',
                            ));
                        } else {
                            $newList = array_values(array_filter(
                                $oldValue,
                                static fn ($v) => $v !== $value,
                            ));
                            $indexed[$attrCode] = $newList;
                            $object->updateAttributeIndex($indexed);
                            $this->em->persist(new BulkLog(
                                $session->getId(),
                                $object->getId(),
                                null,
                                $oldValue,
                                $newList,
                                BulkLog::LEVEL_INFO,
                                null,
                            ));
                            ++$success;
                        }
                    } elseif ($oldValue === $value) {
                        unset($indexed[$attrCode]);
                        $object->updateAttributeIndex($indexed);
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
                    } else {
                        ++$skipped;
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
