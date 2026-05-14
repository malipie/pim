<?php

declare(strict_types=1);

namespace App\Export\Domain\Repository;

use App\Export\Domain\Entity\ExportProfile;
use App\Shared\Domain\Tenant;
use Symfony\Component\Uid\Uuid;

interface ExportProfileRepositoryInterface
{
    public function save(ExportProfile $profile): void;

    public function remove(ExportProfile $profile): void;

    public function findById(Uuid $id): ?ExportProfile;

    /**
     * @return list<ExportProfile>
     */
    public function findByTenantAndUser(Tenant $tenant, Uuid $userId): array;

    public function findByTenantUserAndName(Tenant $tenant, Uuid $userId, string $name): ?ExportProfile;
}
