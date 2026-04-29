<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

/**
 * RBAC voter for association rows. Resource string `'association'` was
 * added to {@see \App\Identity\Domain\Rbac\RbacMatrix} in #41 to keep
 * authorization granular — without it, association write/delete would
 * piggy-back on the broader `'object'` permission and silently let an
 * integration-manager (object read-only) mutate associations.
 */
final class AssociationVoter extends AbstractRbacVoter
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
        return 'association';
    }

    protected function subjectClass(): string
    {
        return 'App\\Catalog\\Domain\\Entity\\Association';
    }
}
