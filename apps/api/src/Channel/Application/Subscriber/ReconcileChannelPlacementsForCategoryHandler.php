<?php

declare(strict_types=1);

namespace App\Channel\Application\Subscriber;

use App\Channel\Application\Message\ReconcileChannelPlacementsForCategory;
use App\Channel\Application\Service\ReconcileObjectChannelPlacements;
use App\Shared\Application\AbstractBatchHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * #1314 — consumes {@see ReconcileChannelPlacementsForCategory}: reconciles the
 * channel placements of every product assigned to a master category after its
 * node mapping changed. Batched ({@see AbstractBatchHandler}) to stay
 * worker-memory-safe for categories with many products.
 */
#[AsMessageHandler]
final class ReconcileChannelPlacementsForCategoryHandler extends AbstractBatchHandler
{
    public function __construct(
        EntityManagerInterface $entityManager,
        private readonly ReconcileObjectChannelPlacements $reconciler,
    ) {
        parent::__construct($entityManager);
    }

    public function __invoke(ReconcileChannelPlacementsForCategory $message): void
    {
        $tenant = $message->tenantId();

        // tenant-safe: explicit tenant_id filter via the joined object row.
        $objectIds = $this->entityManager->getConnection()->fetchFirstColumn(
            'SELECT oc.object_id FROM object_categories oc'
            .' JOIN objects o ON o.id = oc.object_id'
            .' WHERE oc.category_id = CAST(:cat AS uuid) AND o.tenant_id = CAST(:tid AS uuid)',
            ['cat' => $message->masterCategoryId, 'tid' => $message->tenantId],
        );

        $processed = 0;
        foreach ($objectIds as $objectId) {
            if (!\is_string($objectId)) {
                continue;
            }
            $this->reconciler->reconcile(Uuid::fromString($objectId), $tenant);

            if ($this->shouldFlush(++$processed)) {
                $this->flushAndClear();
            }
        }

        $this->flushAndClear();
    }
}
