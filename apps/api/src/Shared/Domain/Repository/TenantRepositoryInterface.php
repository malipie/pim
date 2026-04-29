<?php

declare(strict_types=1);

namespace App\Shared\Domain\Repository;

use App\Shared\Domain\Tenant;
use Symfony\Component\Uid\Uuid;

/**
 * Port for Tenant persistence. The Doctrine implementation lives in
 * Shared\Infrastructure\Doctrine\Repository — Application code MUST depend
 * on this interface, never on the adapter.
 */
interface TenantRepositoryInterface
{
    public function findById(Uuid $id): ?Tenant;

    public function findByCode(string $code): ?Tenant;

    public function save(Tenant $tenant): void;

    public function remove(Tenant $tenant): void;
}
