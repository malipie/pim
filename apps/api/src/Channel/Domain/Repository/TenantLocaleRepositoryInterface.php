<?php

declare(strict_types=1);

namespace App\Channel\Domain\Repository;

use App\Channel\Domain\Entity\Locale;
use App\Channel\Domain\Entity\TenantLocale;
use App\Shared\Domain\Tenant;
use Symfony\Component\Uid\Uuid;

interface TenantLocaleRepositoryInterface
{
    public function findById(Uuid $id): ?TenantLocale;

    public function findByTenantAndLocale(Tenant $tenant, Locale $locale): ?TenantLocale;

    public function findByTenantAndCode(Tenant $tenant, string $code): ?TenantLocale;

    /**
     * @return list<TenantLocale> ordered by sortOrder ASC
     */
    public function findActiveForTenant(Tenant $tenant): array;

    /**
     * @return list<TenantLocale> active + inactive, ordered by sortOrder ASC
     */
    public function findAllForTenant(Tenant $tenant): array;

    public function findDefaultForTenant(Tenant $tenant): ?TenantLocale;

    public function save(TenantLocale $entity): void;

    public function remove(TenantLocale $entity): void;
}
