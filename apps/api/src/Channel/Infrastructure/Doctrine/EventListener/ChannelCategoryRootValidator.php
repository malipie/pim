<?php

declare(strict_types=1);

namespace App\Channel\Infrastructure\Doctrine\EventListener;

use App\Channel\Domain\Entity\Channel;
use App\Channel\Domain\Repository\ChannelCategoryNodeRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use InvalidArgumentException;

/**
 * Enforces that {@see Channel::$categoryTreeRootId}, when set, points at a
 * {@see \App\Channel\Domain\Entity\ChannelCategoryNode} that is the root (no
 * parent) of THIS channel's navigation tree (CHC-01, #1284).
 *
 * Before CHC-01 the soft FK referenced a master `CatalogObject` of
 * kind=category. The navigation tree now lives in its own table, so the
 * validator stays inside the Channel context — the cross-BC Catalog
 * dependency (and its deptrac skip) is gone.
 */
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
final readonly class ChannelCategoryRootValidator
{
    public function __construct(
        private ChannelCategoryNodeRepositoryInterface $nodes,
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

        $node = $this->nodes->findById($rootId);
        if (null === $node) {
            throw new InvalidArgumentException(\sprintf(
                'Channel "%s" navigation root %s does not exist.',
                $channel->getCode(),
                $rootId->toRfc4122(),
            ));
        }

        if (!$node->getChannel()->getId()->equals($channel->getId())) {
            throw new InvalidArgumentException(\sprintf(
                'Channel "%s" navigation root %s belongs to a different channel.',
                $channel->getCode(),
                $rootId->toRfc4122(),
            ));
        }

        if (!$node->isRoot()) {
            throw new InvalidArgumentException(\sprintf(
                'Channel "%s" navigation root %s must be a tree root (no parent).',
                $channel->getCode(),
                $rootId->toRfc4122(),
            ));
        }
    }
}
