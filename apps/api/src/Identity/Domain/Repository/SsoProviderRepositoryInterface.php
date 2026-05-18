<?php

declare(strict_types=1);

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Entity\SsoProvider;
use Symfony\Component\Uid\Uuid;

interface SsoProviderRepositoryInterface
{
    public function findById(Uuid $id): ?SsoProvider;

    public function findByTenantAndKind(Uuid $tenantId, string $kind): ?SsoProvider;

    /**
     * @return list<SsoProvider>
     */
    public function findByTenant(Uuid $tenantId): array;

    public function save(SsoProvider $entity): void;

    public function remove(SsoProvider $entity): void;
}
