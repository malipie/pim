<?php

declare(strict_types=1);

namespace App\Catalog\Application\Subscriber;

use App\Catalog\Contracts\Event\ObjectArchived;
use App\Catalog\Contracts\Event\ObjectAttributesChanged;
use App\Catalog\Contracts\Event\ObjectCreated;
use App\Catalog\Contracts\Event\ObjectPublished;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Placeholder subscriber wired up for {@see \App\Catalog\Contracts\Event}
 * domain events. Each #[AsMessageHandler] is a no-op today; the real work
 * lands in:
 *
 *   - RF-19 — search index sync (Meilisearch incremental updates);
 *   - epic 0.5 — bulk reindex worker.
 *
 * Wiring this stub now means RF-20's
 * {@see \App\Shared\Infrastructure\Messenger\DomainEventToMessageMiddleware}
 * has somewhere to route Catalog events as soon as it ships, without
 * touching the entity again.
 */
final class ObjectIndexedSubscriber
{
    #[AsMessageHandler]
    public function onObjectCreated(ObjectCreated $event): void
    {
        // TODO(RF-19/epic-0.5): index $event->objectId in Meilisearch.
    }

    #[AsMessageHandler]
    public function onObjectPublished(ObjectPublished $event): void
    {
        // TODO(RF-19/epic-0.5): mark $event->objectId as published in the search index.
    }

    #[AsMessageHandler]
    public function onObjectArchived(ObjectArchived $event): void
    {
        // TODO(RF-19/epic-0.5): drop $event->objectId from active search indices.
    }

    #[AsMessageHandler]
    public function onObjectAttributesChanged(ObjectAttributesChanged $event): void
    {
        // TODO(RF-19/epic-0.5): partial reindex of $event->changedAttributeCodes for $event->objectId.
    }
}
