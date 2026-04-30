<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

final class ApiProfileVoter extends AbstractRbacVoter
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
        return 'App\\ApiConfigurator\\Domain\\Entity\\ApiProfile';
    }
}
