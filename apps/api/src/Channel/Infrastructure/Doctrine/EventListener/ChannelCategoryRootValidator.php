<?php

declare(strict_types=1);

namespace App\Channel\Infrastructure\Doctrine\EventListener;

use App\Catalog\Domain\ObjectKind;
use App\Channel\Domain\Entity\Channel;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use InvalidArgumentException;

/**
 * Enforces that {@see Channel::$categoryTreeRoot}, when set, points at a
 * `CatalogObject` of `kind = category`.
 *
 * The schema does not have a CHECK constraint for this — the FK is just
 * `objects.id`, and the kind discriminator lives next to the row, not on
 * the FK side. This listener gives a friendlier error than letting some
 * downstream consumer (channel publisher, category-tree renderer) fail
 * mid-flight on a non-category root.
 */
#[AsDoctrineListener(event: Events::prePersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
final class ChannelCategoryRootValidator
{
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
        $root = $channel->getCategoryTreeRoot();
        if (null === $root) {
            return;
        }

        if (ObjectKind::Category !== $root->getKind()) {
            throw new InvalidArgumentException(\sprintf(
                'Channel "%s" category tree root must be an object with kind=category, got kind=%s.',
                $channel->getCode(),
                $root->getKind()->value,
            ));
        }
    }
}
