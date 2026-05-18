<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Rbac\PermissionSet;

/**
 * RBAC-P3 contract for the {@see PermissionResolver}.
 *
 * Lets Phase 3 consumers (EndpointGuardListener, resource-specific Voters,
 * Phase 3 #671 AttributePermissionPolicy, the Phase 6 audit listener) depend
 * on a doubable abstraction instead of the cache-coupled concrete class.
 * The concrete resolver remains the single implementation; the interface
 * exists so unit tests can swap in fixtures without touching Redis.
 */
interface PermissionResolverInterface
{
    public function resolve(User $user): PermissionSet;
}
