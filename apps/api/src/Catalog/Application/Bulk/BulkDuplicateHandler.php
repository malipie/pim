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

/**
 * VIEW-16 (#548) — `duplicate` bulk action.
 *
 * Clones each source product into `{code}-COPY-N` (mirrors
 * DuplicateProductController). Skips collisions (conflict warning) and
 * caps the suffix counter to 9999 per source. The 24h rollback path
 * removes the cloned rows by id (recorded in BulkLog new_value). Shared
 * lifecycle: {@see AbstractBulkHandler}; cloning N values per source is
 * heavier, so the chunk size is halved.
 */
final class BulkDuplicateHandler extends AbstractBulkHandler
{
    protected const int CHUNK_SIZE = 100;
    public const int MAX_SUFFIX = 9999;

    public function __construct(
        CatalogObjectRepositoryInterface $catalogObjects,
        private readonly ObjectValueRepositoryInterface $values,
        EntityManagerInterface $em,
        BulkContext $bulkContext,
        BulkReindexQueueInterface $reindexQueue,
    ) {
        parent::__construct($catalogObjects, $em, $bulkContext, $reindexQueue);
    }

    /**
     * @return array{success: int, skipped: int, error: int}
     */
    public function handle(BulkSession $session): array
    {
        return $this->runBatch($session);
    }

    protected function processObject(CatalogObject $object, BulkSession $session, BulkCounters $counters): void
    {
        if (ObjectKind::Product !== $object->getKind()) {
            ++$counters->error;

            return;
        }

        if (null === $object->getTenant()) {
            throw new BadRequestHttpException('Source product is missing tenant context.');
        }

        $newCode = $this->allocateCopySku($object);
        if (null === $newCode) {
            ++$counters->skipped;
            $this->em->persist(new BulkLog(
                $session->getId(),
                $object->getId(),
                null,
                ['code' => $object->getCode()],
                ['code' => $object->getCode()],
                BulkLog::LEVEL_WARNING,
                'Suffix exhausted',
            ));

            return;
        }

        $copy = new CatalogObject($object->getObjectType(), $newCode);
        $this->catalogObjects->save($copy);
        $copy->markTouchedByBulkSession($session->getId());

        foreach ($this->values->findByObject($object) as $sourceValue) {
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
            $object->getId(),
            null,
            ['code' => $object->getCode()],
            ['copy_id' => $copy->getId()->toRfc4122(), 'copy_code' => $newCode],
            BulkLog::LEVEL_INFO,
            null,
        ));
        ++$counters->success;
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
