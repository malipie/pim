<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\EventListener;

use App\Shared\Application\TenantContext;
use App\Shared\Application\TenantScoped;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Events;
use LogicException;

/**
 * Stamps every persisted {@see TenantScoped} entity with the current tenant.
 *
 * The listener dispatches by interface, not by FQCN — domain entities opt in
 * by implementing TenantScoped, and the listener picks them up automatically.
 * Sprint-0 hard-coded `instanceof Product`; ticket #30 widened the contract
 * so the next round of catalog entities (`Object`, `Channel`, `Asset` —
 * epic 0.3) plug in without touching this file.
 *
 * Throwing instead of silently leaving tenant_id NULL is deliberate: the
 * column is NOT NULL at the schema level so the persist would fail anyway,
 * but the LogicException carries a much clearer message for the operator.
 *
 * Out of scope on purpose: User (login flow needs to find users globally by
 * email before the tenant is known) and RefreshToken (`tenant_id` set in
 * the service constructor with explicit User context — no listener needed).
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

        if (!$entity instanceof TenantScoped) {
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
