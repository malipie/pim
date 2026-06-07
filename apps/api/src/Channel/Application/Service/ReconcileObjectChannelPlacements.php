<?php

declare(strict_types=1);

namespace App\Channel\Application\Service;

use App\Channel\Domain\ChannelPlacementSource;
use App\Channel\Domain\Repository\ChannelCategoryNodeMappingRepositoryInterface;
use App\Channel\Domain\Repository\ChannelCategoryNodeRepositoryInterface;
use App\Channel\Domain\Repository\ObjectChannelPlacementRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * #1314 — reconcile a product's AUTO channel placements from the node mappings
 * of ALL its master categories (CHC-07 originally used only the primary).
 *
 * Per channel the product lands on a single node (placement is UNIQUE per
 * object+channel); when several of the product's categories map the same
 * channel, precedence is: primary category first, then by assignment
 * `position`. Manual placements (CHC-03) are never overwritten. AUTO
 * placements for channels no longer mapped by any of the product's categories
 * are removed (so dropping a category cleans up its placement).
 */
final readonly class ReconcileObjectChannelPlacements
{
    public function __construct(
        private ObjectChannelPlacementRepositoryInterface $placements,
        private ChannelCategoryNodeMappingRepositoryInterface $mappings,
        private ChannelCategoryNodeRepositoryInterface $nodes,
        private EntityManagerInterface $em,
    ) {
    }

    public function reconcile(Uuid $objectId, Uuid $tenantId): void
    {
        // Desired node per channel, resolved in precedence order (primary first,
        // then assignment position). The first category that maps a channel wins.
        $desired = []; // channelId(rfc4122) => ['channel' => Channel, 'node' => ChannelCategoryNode]
        foreach ($this->categoryIdsInPrecedenceOrder($objectId, $tenantId) as $categoryId) {
            foreach ($this->mappings->findByMasterCategory(Uuid::fromString($categoryId)) as $mapping) {
                $channelId = $mapping->getChannelId()->toRfc4122();
                if (isset($desired[$channelId])) {
                    continue;
                }
                $nodeIds = $mapping->getChannelNodeIds();
                if ([] === $nodeIds) {
                    continue;
                }
                $node = $this->nodes->findById(Uuid::fromString($nodeIds[0]));
                if (null === $node) {
                    continue;
                }
                $desired[$channelId] = ['channel' => $mapping->getChannel(), 'node' => $node];
            }
        }

        $existing = $this->placements->findByObject($objectId);
        $existingByChannel = [];
        foreach ($existing as $placement) {
            $existingByChannel[$placement->getChannelId()->toRfc4122()] = $placement;
        }

        // Upsert desired placements — manual wins.
        foreach ($desired as $channelId => $target) {
            $current = $existingByChannel[$channelId] ?? null;
            if (null !== $current && ChannelPlacementSource::Manual === $current->getSource()) {
                continue;
            }
            $this->placements->upsert($objectId, $target['channel'], $target['node'], ChannelPlacementSource::Auto);
        }

        // Remove stale AUTO placements — channel no longer mapped by any category.
        foreach ($existing as $placement) {
            if (ChannelPlacementSource::Auto === $placement->getSource()
                && !isset($desired[$placement->getChannelId()->toRfc4122()])) {
                $this->placements->remove($placement);
            }
        }
    }

    /**
     * Master category ids the object is assigned to, primary first then by
     * assignment position.
     *
     * @return list<string>
     */
    private function categoryIdsInPrecedenceOrder(Uuid $objectId, Uuid $tenantId): array
    {
        // tenant-safe: explicit tenant_id filter via the joined object row; the
        // object_id is itself a tenant-scoped UUID from the triggering event.
        $rows = $this->em->getConnection()->fetchFirstColumn(
            'SELECT oc.category_id FROM object_categories oc'
            .' JOIN objects o ON o.id = oc.object_id'
            .' WHERE oc.object_id = CAST(:oid AS uuid) AND o.tenant_id = CAST(:tid AS uuid)'
            .' ORDER BY oc.is_primary DESC, oc.position ASC',
            ['oid' => $objectId->toRfc4122(), 'tid' => $tenantId->toRfc4122()],
        );

        $ids = [];
        foreach ($rows as $row) {
            if (\is_string($row)) {
                $ids[] = $row;
            }
        }

        return $ids;
    }
}
