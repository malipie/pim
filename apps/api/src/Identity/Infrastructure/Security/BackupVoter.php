<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Backup\Domain\Entity\Backup;

/**
 * RBAC voter for {@see Backup}. Tenant scoping is enough here — all
 * backups within a tenant share the same operational surface. The
 * "admin only for write" rule from spec §7.8 is enforced by gating
 * the `backup:write` permission to admin roles in seed data, not
 * by an extra ownership predicate on this voter.
 */
final class BackupVoter extends AbstractRbacVoter
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
        return 'backup';
    }

    protected function subjectClass(): string
    {
        return Backup::class;
    }
}
