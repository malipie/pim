<?php

declare(strict_types=1);

namespace App\Import\Domain\Repository;

use App\Import\Domain\Entity\ImportSession;
use App\Shared\Domain\Tenant;
use Symfony\Component\Uid\Uuid;

interface ImportSessionRepositoryInterface
{
    public function save(ImportSession $session): void;

    public function findById(Uuid $id): ?ImportSession;

    /**
     * @return list<ImportSession>
     */
    public function findByTenantAndUser(Tenant $tenant, Uuid $userId, int $limit = 50): array;
}
