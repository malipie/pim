<?php

declare(strict_types=1);

namespace App\Identity\Application\Policy;

/**
 * RBAC-P3-010 (#673) — scope dimension carried by the `?scope=own|all`
 * query parameter on operational resource collections.
 */
enum OwnershipScope: string
{
    case Own = 'own';
    case All = 'all';
}
