<?php

declare(strict_types=1);

namespace App\Identity\Application;

/**
 * Summary returned by {@see RbacSeeder::seed()}.
 *
 * The CLI command renders these numbers and tests assert them — running the
 * seeder a second time should report zero creates and zero updates, which is
 * the guarantee #27 makes about idempotency.
 */
final readonly class RbacSeederReport
{
    public function __construct(
        public int $permissionsCreated,
        public int $rolesCreated,
        public int $rolesUpdated,
    ) {
    }

    public function isNoOp(): bool
    {
        return 0 === $this->permissionsCreated
            && 0 === $this->rolesCreated
            && 0 === $this->rolesUpdated;
    }
}
