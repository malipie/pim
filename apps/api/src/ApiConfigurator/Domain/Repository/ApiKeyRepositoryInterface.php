<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Domain\Repository;

use App\ApiConfigurator\Domain\Entity\ApiKey;
use Symfony\Component\Uid\Uuid;

interface ApiKeyRepositoryInterface
{
    public function save(ApiKey $key): void;

    public function remove(ApiKey $key): void;

    public function findById(Uuid $id): ?ApiKey;

    /**
     * Lookup by the leading 12 chars of the raw key — the cheap step
     * before {@see \App\ApiConfigurator\Domain\Service\ApiKeyHasherInterface::verify()}.
     * Returns at most one row (`(tenant_id, key_prefix)` is unique per
     * the migration) or `null` when no key is registered for the prefix.
     */
    public function findByKeyPrefix(string $keyPrefix): ?ApiKey;
}
