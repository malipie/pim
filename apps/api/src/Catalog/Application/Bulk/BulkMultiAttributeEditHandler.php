<?php

declare(strict_types=1);

namespace App\Catalog\Application\Bulk;

use App\Catalog\Application\BulkContext;
use App\Catalog\Domain\Entity\BulkLog;
use App\Catalog\Domain\Entity\BulkSession;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * VIEW-13 (#545) — `multi_attribute_edit` bulk action.
 *
 * Applies a list of (attr, op, value) tuples to each target in a single
 * transaction per object. Each attribute change emits its own BulkLog
 * (so rollback can replay individually). Supported ops: `set`, `clear`.
 *
 * Cmd+K killer use case (PRD §3.5): „skopiuj manufacturer do brand i
 * ustaw enabled=true dla wszystkich z manufacturer IS NOT EMPTY".
 */
final class BulkMultiAttributeEditHandler
{
    public const int CHUNK_SIZE = 200;

    public function __construct(
        private readonly CatalogObjectRepositoryInterface $catalogObjects,
        private readonly EntityManagerInterface $em,
        private readonly BulkContext $bulkContext,
    ) {
    }

    /**
     * @param list<array{attr: string, op: string, value?: mixed}> $edits
     *
     * @return array{success: int, skipped: int, error: int}
     */
    public function handle(BulkSession $session, array $edits): array
    {
        if ([] === $edits) {
            throw new BadRequestHttpException('edits must be a non-empty list.');
        }

        $this->bulkContext->setBulk(true);
        try {
            $success = 0;
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
                    $rowChanged = false;
                    foreach ($edits as $edit) {
                        $code = $edit['attr'];
                        $op = $edit['op'];
                        $oldValue = $indexed[$code] ?? null;

                        if ('set' === $op) {
                            $newValue = $edit['value'] ?? null;
                            $indexed[$code] = $newValue;
                        } elseif ('clear' === $op) {
                            $newValue = null;
                            unset($indexed[$code]);
                        } else {
                            $this->em->persist(new BulkLog(
                                $session->getId(),
                                $object->getId(),
                                null,
                                $oldValue,
                                $oldValue,
                                BulkLog::LEVEL_ERROR,
                                \sprintf('Unsupported edit op "%s" on attr "%s"', $op, $code),
                            ));
                            continue;
                        }

                        $this->em->persist(new BulkLog(
                            $session->getId(),
                            $object->getId(),
                            null,
                            $oldValue,
                            $newValue,
                            BulkLog::LEVEL_INFO,
                            $code,
                        ));
                        $rowChanged = true;
                    }

                    if ($rowChanged) {
                        $object->updateAttributeIndex($indexed);
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

            $session->complete($success, 0, $errors);
            $this->em->persist($session);
            $this->em->flush();

            return ['success' => $success, 'skipped' => 0, 'error' => $errors];
        } finally {
            $this->bulkContext->setBulk(false);
        }
    }
}
