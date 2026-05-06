<?php

declare(strict_types=1);

namespace App\Import\Domain\Repository;

use App\Import\Domain\Entity\ImportProfile;
use App\Shared\Domain\Tenant;
use Symfony\Component\Uid\Uuid;

interface ImportProfileRepositoryInterface
{
    public function save(ImportProfile $profile): void;

    public function remove(ImportProfile $profile): void;

    public function findById(Uuid $id): ?ImportProfile;

    public function findByName(Tenant $tenant, Uuid $userId, string $name): ?ImportProfile;

    /**
     * @return list<ImportProfile>
     */
    public function findByTenantAndUser(Tenant $tenant, Uuid $userId): array;
}
