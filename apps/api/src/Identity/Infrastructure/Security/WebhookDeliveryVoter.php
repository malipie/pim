<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

/**
 * RBAC voter for the producer `WebhookDelivery` read resource (APIC-P4-06).
 * Reuses the `api_profile` RbacMatrix resource — webhook deliveries belong to
 * the producer surface gated alongside ApiProfile/ApiKey. The subject is named
 * by its FQCN string (no cross-BC class import), so this stays Deptrac-clean.
 */
final class WebhookDeliveryVoter extends AbstractRbacVoter
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
        return 'App\\ApiConfigurator\\Domain\\Entity\\WebhookDelivery';
    }
}
