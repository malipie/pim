<?php

declare(strict_types=1);

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Entity\ApiToken;
use Symfony\Component\Uid\Uuid;

interface ApiTokenRepositoryInterface
{
    public function findById(Uuid $id): ?ApiToken;

    public function findByHash(string $tokenHash): ?ApiToken;

    /**
     * @return list<ApiToken>
     */
    public function findByUser(Uuid $userId): array;

    /**
     * @return list<ApiToken>
     */
    public function findByTenant(Uuid $tenantId): array;

    public function save(ApiToken $entity): void;

    public function remove(ApiToken $entity): void;
}
