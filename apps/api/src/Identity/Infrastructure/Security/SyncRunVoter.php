<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

/**
 * RBAC voter for the API Configurator SyncRun read resource (APIC-P4-01).
 * Reuses the legacy `integration` RbacMatrix resource — the same surface that
 * gates the rest of the consumer. The subject is named by its FQCN string (no
 * cross-BC class import), so this stays Deptrac-clean.
 */
final class SyncRunVoter extends AbstractRbacVoter
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
        return 'App\\Integration\\Generic\\Domain\\Entity\\SyncRun';
    }
}
