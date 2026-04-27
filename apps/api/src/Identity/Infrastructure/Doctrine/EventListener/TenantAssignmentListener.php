<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Doctrine\EventListener;

use App\Catalog\Domain\Entity\Product;
use App\Identity\Application\TenantContext;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use LogicException;

/**
 * Stamps every persisted domain entity with the current tenant.
 *
 * Today the listener only knows about Product because that is the only
 * tenant-scoped entity in Sprint 0. As new entities are introduced the
 * listener receives them through a registry — see ticket #30 (0.2.7) which
 * formalises this for every domain context.
 *
 * Throwing instead of silently leaving tenant_id NULL is deliberate: the
 * column is NOT NULL at the schema level so the persist would fail anyway,
 * but the LogicException carries a much clearer message for the operator.
 */
#[AsDoctrineListener(event: Events::prePersist)]
final readonly class TenantAssignmentListener
{
    public function __construct(
        private TenantContext $tenantContext,
    ) {
    }

    public function prePersist(PrePersistEventArgs $event): void
    {
        $entity = $event->getObject();

        if (!$entity instanceof Product) {
            return;
        }

        if (null !== $entity->getTenant()) {
            return;
        }

        $tenant = $this->tenantContext->get();

        if (null === $tenant) {
            throw new LogicException(\sprintf(
                'Cannot persist %s without a current tenant. Set TenantContext at request entry, in fixtures, or in tests.',
                $entity::class,
            ));
        }

        $entity->assignTenant($tenant);
    }
}
