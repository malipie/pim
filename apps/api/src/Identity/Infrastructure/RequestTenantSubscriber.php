<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure;

use App\Identity\Application\CurrentTenantProvider;
use App\Identity\Application\TenantContext;
use App\Identity\Infrastructure\Doctrine\Filter\TenantFilterConfigurator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Hydrates TenantContext from the resolved current tenant at the start of
 * every HTTP request and clears it on terminate.
 *
 * Worker mode (FrankenPHP) keeps state between requests; the explicit
 * clear-on-terminate prevents one tenant's data from leaking into the
 * following request when the security stack happens to fail before the
 * next request rebinds the context.
 */
final readonly class RequestTenantSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CurrentTenantProvider $currentTenantProvider,
        private TenantContext $tenantContext,
        private TenantFilterConfigurator $filterConfigurator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Before controllers — listener priority must be higher than the
            // firewall (which also runs on REQUEST) so the security token is
            // already populated. Symfony firewall runs at priority 8 in 7.x.
            KernelEvents::REQUEST => ['onRequest', 4],
            KernelEvents::TERMINATE => ['onTerminate', -255],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$this->isMainRequest($event)) {
            return;
        }

        $tenant = $this->currentTenantProvider->getCurrent();
        if (null !== $tenant) {
            $this->tenantContext->set($tenant);
            $this->filterConfigurator->apply();
        }
    }

    public function onTerminate(TerminateEvent $event): void
    {
        if (!$this->isMainRequest($event)) {
            return;
        }

        $this->tenantContext->clear();
        $this->filterConfigurator->apply();
    }

    private function isMainRequest(KernelEvent $event): bool
    {
        return $event->isMainRequest();
    }
}
