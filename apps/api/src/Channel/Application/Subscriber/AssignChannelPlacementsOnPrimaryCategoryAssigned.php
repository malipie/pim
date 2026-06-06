<?php

declare(strict_types=1);

namespace App\Channel\Application\Subscriber;

use App\Catalog\Contracts\Event\ObjectPrimaryCategoryAssigned;
use App\Channel\Domain\ChannelPlacementSource;
use App\Channel\Domain\Repository\ChannelCategoryNodeMappingRepositoryInterface;
use App\Channel\Domain\Repository\ChannelCategoryNodeRepositoryInterface;
use App\Channel\Domain\Repository\ObjectChannelPlacementRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * CHC-07 (#1290) — auto-assign channel placements from node mappings.
 *
 * On {@see ObjectPrimaryCategoryAssigned}: for every channel that mapped the
 * product's new primary master category ({@see \App\Channel\Domain\Entity\ChannelCategoryNodeMapping},
 * CHC-06), create or re-point an `object_channel_placements` row with
 * `source='auto'`. The operator sets one master category and the product
 * lands on every channel automatically.
 *
 * Manual placements win: a row the operator set by hand (`source='manual'`,
 * CHC-03) is never overwritten — the operator's intent always overrides the
 * mapping. Re-running is idempotent (upsert + manual skip).
 *
 * Runs on the async transport (see messenger.yaml); the event is
 * {@see \App\Shared\Application\TenantAwareMessage} so the worker rebinds the
 * tenant context before the tenant-filtered repositories run.
 *
 * Mapping is M:N on the channel side (one master → several channel nodes), a
 * placement is single-node per (object, channel). When a mapping lists more
 * than one node we place the product on the first; the operator can re-point
 * by hand afterwards (manual then wins). Deliberate CHC-07 simplification —
 * the split-view UI (CHC-08) makes the per-channel node explicit.
 */
#[AsMessageHandler]
final readonly class AssignChannelPlacementsOnPrimaryCategoryAssigned
{
    public function __construct(
        private ChannelCategoryNodeMappingRepositoryInterface $mappings,
        private ChannelCategoryNodeRepositoryInterface $nodes,
        private ObjectChannelPlacementRepositoryInterface $placements,
    ) {
    }

    public function __invoke(ObjectPrimaryCategoryAssigned $event): void
    {
        $objectId = $event->objectId;

        foreach ($this->mappings->findByMasterCategory($event->primaryCategoryId) as $mapping) {
            $nodeIds = $mapping->getChannelNodeIds();
            if ([] === $nodeIds) {
                continue;
            }

            $node = $this->nodes->findById(Uuid::fromString($nodeIds[0]));
            if (null === $node) {
                // Stale mapping pointing at a deleted node — skip rather than fail.
                continue;
            }

            $channel = $mapping->getChannel();
            $existing = $this->placements->findByObjectAndChannel($objectId, $channel->getId());
            if (null !== $existing && ChannelPlacementSource::Manual === $existing->getSource()) {
                // Operator placed this product by hand — manual wins over auto.
                continue;
            }

            $this->placements->upsert($objectId, $channel, $node, ChannelPlacementSource::Auto);
        }
    }
}
