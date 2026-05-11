<?php

declare(strict_types=1);

namespace App\Import\Domain\Repository;

use App\Import\Domain\Entity\ImportSource;
use App\Shared\Domain\Tenant;
use Symfony\Component\Uid\Uuid;

interface ImportSourceRepositoryInterface
{
    public function save(ImportSource $source): void;

    public function remove(ImportSource $source): void;

    public function findById(Uuid $id): ?ImportSource;

    public function findByCode(Tenant $tenant, string $code): ?ImportSource;

    /**
     * @return list<ImportSource>
     */
    public function findByTenant(Tenant $tenant): array;
}
