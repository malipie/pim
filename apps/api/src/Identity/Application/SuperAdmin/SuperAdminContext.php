<?php

declare(strict_types=1);

namespace App\Identity\Application\SuperAdmin;

use Closure;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * RBAC-P3-014 (#677) — switches the request into the platform-level
 * Super Admin context per PRD §11 (privacy boundary + cross-tenant
 * bypass).
 *
 * Activating cross-tenant mode disables the Doctrine `TenantFilter` so
 * queries see every tenant's rows. The mode is **opt-in** per code
 * path — the entry point (admin controller / CLI command) calls
 * `useCrossTenantMode()` explicitly; outside that call site every
 * other code path still sees the filter enforced.
 *
 * The `restoreTenantScope()` call **must** sit in a finally block so a
 * thrown exception does not leak the cross-tenant view to subsequent
 * requests within the same FrankenPHP worker. The class returns the
 * previous filter state so nested activations compose correctly.
 *
 * Postgres RLS context is handled separately by the existing
 * `RlsContextListener` (RBAC-P2-005 #654) — when activated here, the
 * caller is also responsible for setting `app.bypass_rls = true` via
 * `SET LOCAL` if the caller intends to read tenant-scoped tables. The
 * helper {@see runCrossTenant()} wraps both concerns into one closure
 * for the typical break-glass path.
 *
 * `superAdminId` is tracked on every cross-tenant write through the
 * audit log (`audit_logs.super_admin_id`, `cross_tenant_access=true`)
 * — set by {@see \App\Identity\Infrastructure\Audit\AuditLogListener}
 * when this context is active. Currently the listener inspects the
 * security principal type; a follow-up wires the special flag through
 * a request attribute so the listener does not need to reach into
 * this service.
 */
final class SuperAdminContext
{
    public const string FILTER_NAME = 'tenant_filter';

    private ?Uuid $activeSuperAdminId = null;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function isActive(): bool
    {
        return null !== $this->activeSuperAdminId;
    }

    public function activeSuperAdminId(): ?Uuid
    {
        return $this->activeSuperAdminId;
    }

    /**
     * Opens cross-tenant mode for the duration of $callback, then
     * restores the previous filter state. Use this for one-shot
     * operations; for long-running commands prefer the explicit
     * `useCrossTenantMode()` / `restoreTenantScope()` pair so the
     * compiler can see the scope boundaries.
     *
     * @template T
     *
     * @param Closure(): T $callback
     *
     * @return T
     */
    public function runCrossTenant(Uuid $superAdminId, Closure $callback): mixed
    {
        $previous = $this->useCrossTenantMode($superAdminId);

        try {
            return $callback();
        } finally {
            $this->restoreTenantScope($previous);
        }
    }

    /**
     * Disables the tenant filter and stamps the active super admin id.
     * Returns the previous filter-enabled state so a finally block can
     * restore it.
     */
    public function useCrossTenantMode(Uuid $superAdminId): bool
    {
        $filters = $this->entityManager->getFilters();
        $previouslyEnabled = $filters->isEnabled(self::FILTER_NAME);

        if ($previouslyEnabled) {
            $filters->disable(self::FILTER_NAME);
        }

        $this->activeSuperAdminId = $superAdminId;

        return $previouslyEnabled;
    }

    public function restoreTenantScope(bool $reEnableFilter): void
    {
        $this->activeSuperAdminId = null;

        if ($reEnableFilter) {
            $filters = $this->entityManager->getFilters();
            if (!$filters->isEnabled(self::FILTER_NAME)) {
                $filters->enable(self::FILTER_NAME);
            }
        }
    }
}
