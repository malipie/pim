<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

final class ChannelVoter extends AbstractRbacVoter
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
        return 'channel';
    }

    protected function subjectClass(): string
    {
        return 'App\\Channel\\Domain\\Entity\\Channel';
    }
}
