<?php

declare(strict_types=1);

namespace App\Shared\Application;

/**
 * Marker for entities that may exist as system-shipped rows
 * (`tenant_id IS NULL`) alongside tenant-owned ones.
 *
 * The shared {@see \App\Shared\Infrastructure\Doctrine\EventListener\TenantAssignmentListener}
 * checks {@see self::isSystem()} before resolving the current tenant —
 * system rows stay tenant-less without forcing a `TenantContext` set in
 * boot/test code paths that do not otherwise need one (seeders, fixtures).
 *
 * Entities implementing this contract still implement {@see TenantScoped}
 * because their tenant-owned siblings need tenant scoping; the system
 * lane is just opted-out at the listener layer.
 */
interface SystemShipped
{
    public function isSystem(): bool;
}
