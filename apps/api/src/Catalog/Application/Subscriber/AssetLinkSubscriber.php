<?php

declare(strict_types=1);

namespace App\Catalog\Application\Subscriber;

use App\Asset\Contracts\Event\AssetUploaded;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Catalog reaction to {@see AssetUploaded}.
 *
 * Lives here, not in Asset/, because the work it eventually does belongs
 * to Catalog (denormalize storage URL into the linked CatalogObject's
 * `attributes_indexed` so search/exporters do not have to JOIN). For
 * now, an explicit subscriber proves the cross-BC contract: Asset
 * publishes events, Catalog consumes them, no Domain reach-over.
 *
 * Real denormalization lands together with the search index sync
 * (RF-19 / epic 0.5).
 */
final class AssetLinkSubscriber
{
    #[AsMessageHandler]
    public function onAssetUploaded(AssetUploaded $event): void
    {
        // TODO(RF-19/epic-0.5): if $event->linkedObjectId is set, refresh
        //   the linked CatalogObject's attributes_indexed media block.
    }
}
