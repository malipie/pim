<?php

declare(strict_types=1);

namespace App\Identity\Domain\Rbac;

/**
 * Static description of one permission row in the seeder matrix.
 *
 * Resources land here as they are introduced by their owning bounded context;
 * the seeder ignores entities that don't exist yet at runtime — what counts is
 * the (resource, action) pair, not whether a Doctrine entity backs it. This
 * means voters can reference `Permission` rows that anticipate Object/Channel
 * arriving in epic 0.3/0.6 without blocking the seeder on missing tables.
 */
final readonly class PermissionDefinition
{
    public function __construct(
        public string $resource,
        public string $action,
    ) {
    }

    public function code(): string
    {
        return \sprintf('%s.%s', $this->resource, $this->action);
    }
}
