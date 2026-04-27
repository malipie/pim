<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Doctrine\Filter;

use App\Catalog\Domain\Entity\Product;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;
use InvalidArgumentException;

/**
 * Appends `<table>.tenant_id = :current_tenant` to every SQL query touching a
 * tenant-scoped entity. The parameter is set per request by TenantFilterConfigurator
 * once the tenant is known.
 *
 * RLS at the Postgres level is the second line of defence — activated in phase 1
 * (sekcja 11.1a architektury). Until then this filter is the sole isolation
 * mechanism for application-layer queries; native SQL bypassing Doctrine still
 * sees every tenant. Smoke test #12 (0.0.12) validates the application-layer
 * boundary holds.
 *
 * Bulk operations (`COPY`, raw INSERT … SELECT) bypass this filter by design.
 * The runbook for those workflows (sekcja 7 architektury) calls out the need
 * to disable RLS explicitly when it is enabled.
 */
final class TenantFilter extends SQLFilter
{
    public const string PARAMETER = 'current_tenant';

    /** @var array<class-string, true> */
    private const array TENANT_SCOPED_ENTITIES = [
        Product::class => true,
    ];

    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!isset(self::TENANT_SCOPED_ENTITIES[$targetEntity->getName()])) {
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

        return \sprintf('%s.tenant_id = %s', $targetTableAlias, $value);
    }
}
