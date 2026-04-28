<?php

declare(strict_types=1);

namespace App\Identity\Domain\Rbac;

/**
 * Static description of one global role in the seeder matrix.
 *
 * `permissionCodes` references the matrix from {@see RbacMatrix}; the seeder
 * resolves them to {@see \App\Identity\Domain\Entity\Permission} rows after
 * the permission pass. Unknown codes are an error, not a warning — typos in
 * the matrix would otherwise create silently under-permissioned roles.
 */
final readonly class RoleDefinition
{
    /**
     * @param list<string> $permissionCodes
     */
    public function __construct(
        public string $code,
        public string $name,
        public array $permissionCodes,
    ) {
    }
}
