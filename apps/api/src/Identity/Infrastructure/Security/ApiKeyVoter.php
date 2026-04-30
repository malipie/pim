<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

/**
 * ApiKey RBAC scopes under the `api_profile` resource — admin profiles
 * and admin keys are the same authority axis (an integration manager
 * who can configure profiles can also mint keys for them). Reusing the
 * resource keeps RbacMatrix grants tight; ACL split moves to a
 * separate resource if/when key ops need a dedicated role.
 */
final class ApiKeyVoter extends AbstractRbacVoter
{
    /**
     * @return array<string, string>
     */
    protected function attributeMap(): array
    {
        return [
            'READ' => 'read',
            'CREATE' => 'write',
            'UPDATE' => 'write',
            'WRITE' => 'write',
            'DELETE' => 'delete',
        ];
    }

    protected function resource(): string
    {
        return 'api_profile';
    }

    protected function subjectClass(): string
    {
        return 'App\\ApiConfigurator\\Domain\\Entity\\ApiKey';
    }
}
