<?php

declare(strict_types=1);

namespace App\Shared\Application\Auth;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Marker for an API-key principal. Lets cross-BC code (voters, tenant
 * resolvers) recognize a machine-issued auth context without
 * importing the concrete `ApiConfigurator` user class — keeps the
 * Identity layer free of upstream dependencies (Deptrac).
 */
interface ApiKeyPrincipal extends UserInterface
{
    public function tenantId(): Uuid;

    /**
     * Profile codes this key may scope to (per ADR-0016).
     *
     * @return list<string>
     */
    public function scopes(): array;
}
