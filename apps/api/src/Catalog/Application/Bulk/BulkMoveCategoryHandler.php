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

/**
 * VIEW-14 (#546) — `move_category` bulk action.
 *
 * Full-replace assignment list. Wipes existing rows and re-inserts the
 * supplied target ids in a single transaction per product via the
 * repository's `replaceForProduct`. Used by the *„Przenieś kategorię"*
 * mockup flow when the operator picks a fresh taxonomy slot. Shared
 * lifecycle: {@see AbstractBulkHandler}.
 */
final class BulkMoveCategoryHandler extends AbstractBulkHandler
{
    /** @var list<string> */
    private array $categoryIds = [];
    private ?Uuid $primaryId = null;
    /** @var list<Uuid> */
    private array $targetUuids = [];

    public function __construct(
        CatalogObjectRepositoryInterface $catalogObjects,
        private readonly ObjectCategoryRepositoryInterface $objectCategories,
        EntityManagerInterface $em,
        BulkContext $bulkContext,
        BulkReindexQueueInterface $reindexQueue,
    ) {
        parent::__construct($catalogObjects, $em, $bulkContext, $reindexQueue);
    }

    /**
     * @param list<string> $categoryIds
     *
     * @return array{success: int, skipped: int, error: int}
     */
    public function handle(BulkSession $session, array $categoryIds): array
    {
        $this->categoryIds = $categoryIds;
        $this->primaryId = [] === $categoryIds ? null : Uuid::fromString($categoryIds[0]);
        $this->targetUuids = array_map(static fn (string $id) => Uuid::fromString($id), $categoryIds);

        return $this->runBatch($session);
    }

    protected function processObject(CatalogObject $object, BulkSession $session, BulkCounters $counters): void
    {
        $existing = $this->objectCategories->findByProduct($object);
        $existingIds = array_map(
            static fn (ObjectCategory $oc): string => $oc->getCategory()->getId()->toRfc4122(),
            $existing,
        );

        $this->objectCategories->replaceForProduct($object, $this->targetUuids, $this->primaryId);
        $object->markTouchedByBulkSession($session->getId());

        $this->em->persist(new BulkLog(
            $session->getId(),
            $object->getId(),
            null,
            $existingIds,
            $this->categoryIds,
            BulkLog::LEVEL_INFO,
            null,
        ));
        ++$counters->success;
    }
}
