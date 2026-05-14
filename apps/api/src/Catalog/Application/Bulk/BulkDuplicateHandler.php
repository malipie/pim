<?php

declare(strict_types=1);

namespace App\Catalog\Application\Bulk;

use App\Catalog\Application\BulkContext;
use App\Catalog\Application\Reindex\BulkReindexQueueInterface;
use App\Catalog\Domain\Entity\BulkLog;
use App\Catalog\Domain\Entity\BulkSession;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectValue;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Provenance;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectValueRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * VIEW-16 (#548) — `duplicate` bulk action.
 *
 * Clones each source product into `{code}-COPY-N` (mirrors
 * DuplicateProductController). Skips collisions (conflict warning) and
 * caps the suffix counter to 9999 per source. The 24h rollback path
 * removes the cloned rows by id (recorded in BulkLog new_value).
 */
final class BulkDuplicateHandler
{
    public const int CHUNK_SIZE = 100;
    public const int MAX_SUFFIX = 9999;

    public function __construct(
        private readonly CatalogObjectRepositoryInterface $catalogObjects,
        private readonly ObjectValueRepositoryInterface $values,
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
        $this->bulkContext->setBulk(true, $session->getId());
        try {
            $success = 0;
            $skipped = 0;
            $errors = 0;
            $chunkCounter = 0;

            foreach ($session->getTargetObjectIds() as $targetId) {
                try {
                    $source = $this->catalogObjects->findById(Uuid::fromString($targetId));
                    if (!$source instanceof CatalogObject || ObjectKind::Product !== $source->getKind()) {
                        ++$errors;
                        ++$chunkCounter;
                        continue;
                    }

                    $tenant = $source->getTenant();
                    if (null === $tenant) {
                        throw new BadRequestHttpException('Source product is missing tenant context.');
                    }

                    $newCode = $this->allocateCopySku($source);
                    if (null === $newCode) {
                        ++$skipped;
                        $this->em->persist(new BulkLog(
                            $session->getId(),
                            $source->getId(),
                            null,
                            ['code' => $source->getCode()],
                            ['code' => $source->getCode()],
                            BulkLog::LEVEL_WARNING,
                            'Suffix exhausted',
                        ));
                        ++$chunkCounter;
                        continue;
                    }

                    $copy = new CatalogObject($source->getObjectType(), $newCode);
                    $this->catalogObjects->save($copy);
                    $copy->markTouchedByBulkSession($session->getId());

                    foreach ($this->values->findByObject($source) as $sourceValue) {
                        $cloned = new ObjectValue(
                            object: $copy,
                            attribute: $sourceValue->getAttribute(),
                            value: $sourceValue->getValue(),
                            provenance: Provenance::Manual,
                        );
                        $cloned->changeChannelId($sourceValue->getChannelId());
                        $cloned->changeLocale($sourceValue->getLocale());
                        $this->values->save($cloned);
                    }

                    $this->em->persist(new BulkLog(
                        $session->getId(),
                        $source->getId(),
                        null,
                        ['code' => $source->getCode()],
                        ['copy_id' => $copy->getId()->toRfc4122(), 'copy_code' => $newCode],
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

            $this->reindexQueue->queueAll($session->getTargetObjectIds());

            return ['success' => $success, 'skipped' => $skipped, 'error' => $errors];
        } finally {
            $this->bulkContext->setBulk(false);
        }
    }

    private function allocateCopySku(CatalogObject $source): ?string
    {
        $tenant = $source->getTenant();
        if (null === $tenant) {
            return null;
        }
        $base = $source->getCode();
        $counter = 1;
        while ($counter <= self::MAX_SUFFIX) {
            $candidate = \sprintf('%s-COPY-%d', $base, $counter);
            if (null === $this->catalogObjects->findByCode($candidate, ObjectKind::Product, $tenant)) {
                return $candidate;
            }
            ++$counter;
        }

        return null;
    }
}
