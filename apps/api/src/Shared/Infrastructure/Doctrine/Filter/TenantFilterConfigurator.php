<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Filter;

use App\Shared\Application\TenantContext;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Wires the TenantContext value into Doctrine's enabled SQL filter.
 *
 * Doctrine filters can hold parameters but they are scalar — we cannot pass an
 * entity directly. We resolve the active tenant once and forward only the UUID
 * string to the filter. Callers (request listener, fixtures, tests) push the
 * tenant into TenantContext and then call this configurator before issuing
 * queries against tenant-scoped entities.
 */
final readonly class TenantFilterConfigurator
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TenantContext $tenantContext,
    ) {
    }

    public function apply(): void
    {
        $tenant = $this->tenantContext->get();

        $filters = $this->entityManager->getFilters();

        if (null === $tenant) {
            if ($filters->isEnabled('tenant')) {
                $filters->disable('tenant');
            }

            return;
        }

        $filter = $filters->isEnabled('tenant') ? $filters->getFilter('tenant') : $filters->enable('tenant');
        $filter->setParameter(TenantFilter::PARAMETER, $tenant->getId()->toRfc4122());
    }
}
