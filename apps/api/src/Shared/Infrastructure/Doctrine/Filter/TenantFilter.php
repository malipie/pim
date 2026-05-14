<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Filter;

use App\Shared\Application\SystemShipped;
use App\Shared\Application\TenantScoped;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use InvalidArgumentException;

/**
 * Appends `<table>.tenant_id = :current_tenant` to every SQL query touching a
 * {@see TenantScoped} entity. The parameter is set per request by
 * {@see TenantFilterConfigurator} once the tenant
 * is known.
 *
 * RLS at the Postgres level is the second line of defence — policies are
 * created in migration `Version20260428...` (#30 / 0.2.7) but not enabled in
 * MVP. Activation lands in phase 2 along with multi-tenant SaaS go-live
 * (sekcja 11.1a architektury). Until then this filter is the sole isolation
 * mechanism for application-layer queries; native SQL bypassing Doctrine
 * still sees every tenant. Smoke test #12 (0.0.12) validates the
 * application-layer boundary holds.
 *
 * Bulk operations (`COPY`, raw `INSERT … SELECT`) bypass this filter by
 * design — see `docs/multi-tenancy.md` § "Bulk operations" for the runbook.
 *
 * Generalisation note (#30): we used to maintain a class-string allowlist.
 * Switching to `is_subclass_of` against the marker interface lets new
 * domain entities opt in without modifying this file. The runtime cost is
 * a single string-class lookup per query — negligible vs. the SQL roundtrip.
 */
final class TenantFilter extends SQLFilter
{
    public const string PARAMETER = 'current_tenant';

    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!is_subclass_of($targetEntity->getName(), TenantScoped::class, true)) {
            return '';
        }

        try {
            $value = $this->getParameter(self::PARAMETER);
        } catch (InvalidArgumentException) {
            return '';
        }

        if ('' === $value || 'NULL' === $value) {
            return '';
        }

        // Entities with a SystemShipped lane (built-ins shared across
        // tenants via `tenant_id IS NULL`) must surface those rows to
        // every tenant alongside the tenant's own entries.
        if (is_subclass_of($targetEntity->getName(), SystemShipped::class, true)) {
            return \sprintf(
                '(%s.tenant_id = %s OR %s.tenant_id IS NULL)',
                $targetTableAlias,
                $value,
                $targetTableAlias,
            );
        }

        return \sprintf('%s.tenant_id = %s', $targetTableAlias, $value);
    }
}
