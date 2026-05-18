<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Doctrine;

use App\Shared\Application\TenantContext;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * RBAC-P2-005 (#654) — wires the Postgres session-local variables that
 * the RLS policies read.
 *
 * Per-request flow (kernel.request, priority 0 — after Symfony Security
 * has resolved the principal but before any controller / Doctrine work):
 *   1. Read current TenantContext (set by CurrentTenantProvider or
 *      RbacApiTokenAuthenticator earlier in the request lifecycle).
 *   2. Issue `SET LOCAL app.current_tenant = '<uuid>'` so the RLS
 *      policy `tenant_id = current_setting(...)::uuid` evaluates.
 *   3. Cross-tenant Super Admin entry points set
 *      `app.is_super_admin = 'true'` via the same listener API
 *      (called explicitly from Phase 3 #677 break-glass paths).
 *
 * Connection pooling note (pgBouncer transaction mode):
 *   SET LOCAL is transaction-bound, so the variable resets when the
 *   pool checks the connection back in. Subsequent connection checkouts
 *   see fresh values without leaking the previous request's tenant.
 *
 * Bypass safety: if no tenant is resolved (unauthenticated request to
 * /api/docs, /api/health, etc.) the variable is unset and the RLS
 * policies strip every row. That is correct — those endpoints do not
 * read tenant-scoped data, so an unset variable is fail-safe.
 */
final class RlsContextListener
{
    public function __construct(
        private readonly Connection $connection,
        private readonly TenantContext $tenantContext,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 0)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $tenant = $this->tenantContext->get();
        if (null === $tenant) {
            // No tenant resolved — unset the session-local variable so any
            // accidental query through the connection returns 0 rows from
            // the RLS-protected tables.
            $this->connection->executeStatement("SELECT set_config('app.current_tenant', '', true)");
            $this->connection->executeStatement("SELECT set_config('app.is_super_admin', 'false', true)");

            return;
        }

        $this->connection->executeStatement(
            "SELECT set_config('app.current_tenant', :tenant_id, true)",
            ['tenant_id' => $tenant->getId()->toRfc4122()],
        );
        $this->connection->executeStatement("SELECT set_config('app.is_super_admin', 'false', true)");
    }

    /**
     * Public entry point for the Phase 3 Super Admin bypass flow (#677).
     * Sets `app.is_super_admin = 'true'` for the remainder of the
     * current transaction.
     */
    public function enableSuperAdminBypass(): void
    {
        $this->connection->executeStatement("SELECT set_config('app.is_super_admin', 'true', true)");
    }
}
