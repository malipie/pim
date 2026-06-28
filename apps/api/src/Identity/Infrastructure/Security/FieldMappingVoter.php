<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

/**
 * RBAC voter for the API Configurator `FieldMapping` resource (APIC-P2-08).
 * Reuses the legacy `integration` RbacMatrix resource. The subject is named by
 * its FQCN string (no cross-BC class import), keeping this Deptrac-clean.
 */
final class FieldMappingVoter extends AbstractRbacVoter
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
        return 'integration';
    }

    protected function subjectClass(): string
    {
        return 'App\\Integration\\Generic\\Domain\\Entity\\FieldMapping';
    }
}
