<?php

declare(strict_types=1);

namespace App\Channel\Infrastructure\Doctrine\EventListener;

use App\Catalog\Application\Query\GetObjectSummary\GetObjectSummaryHandler;
use App\Catalog\Application\Query\GetObjectSummary\GetObjectSummaryQuery;
use App\Catalog\Domain\ObjectKind;
use App\Channel\Domain\Entity\Channel;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use InvalidArgumentException;

/**
 * Enforces that {@see Channel::$categoryTreeRootId}, when set, points at a
 * `CatalogObject` of `kind = category`.
 *
 * After RF-19 the FK is just a Uuid column on Channel — Catalog identity
 * is not reachable through Doctrine's lazy-loaded entity graph anymore,
 * so the listener pulls a {@see \App\Catalog\Contracts\Query\ObjectSummary}
 * via the cross-BC query handler instead. The schema-level FK still keeps
 * orphans out; this listener gives a friendlier error message before the
 * row reaches downstream consumers (channel publisher, category-tree
 * renderer).
 */
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
final readonly class ChannelCategoryRootValidator
{
    public function __construct(
        private GetObjectSummaryHandler $objectSummary,
    ) {
    }

    public function prePersist(PrePersistEventArgs $event): void
    {
        $entity = $event->getObject();
        if ($entity instanceof Channel) {
            $this->assertRoot($entity);
        }
    }

    public function preUpdate(PreUpdateEventArgs $event): void
    {
        $entity = $event->getObject();
        if ($entity instanceof Channel) {
            $this->assertRoot($entity);
        }
    }

    private function assertRoot(Channel $channel): void
    {
        $rootId = $channel->getCategoryTreeRootId();
        if (null === $rootId) {
            return;
        }

        $summary = ($this->objectSummary)(new GetObjectSummaryQuery($rootId));
        if (null === $summary) {
            throw new InvalidArgumentException(\sprintf(
                'Channel "%s" category tree root %s does not exist.',
                $channel->getCode(),
                $rootId->toRfc4122(),
            ));
        }

        if (ObjectKind::Category !== $summary->kind) {
            throw new InvalidArgumentException(\sprintf(
                'Channel "%s" category tree root must be an object with kind=category, got kind=%s.',
                $channel->getCode(),
                $summary->kind->value,
            ));
        }
    }
}
