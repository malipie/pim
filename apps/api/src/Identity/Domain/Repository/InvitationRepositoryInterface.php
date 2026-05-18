<?php

declare(strict_types=1);

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Entity\Invitation;
use Symfony\Component\Uid\Uuid;

interface InvitationRepositoryInterface
{
    public function findById(Uuid $id): ?Invitation;

    public function findByHash(string $tokenHash): ?Invitation;

    /**
     * @return list<Invitation>
     */
    public function findByTenant(Uuid $tenantId): array;

    /**
     * @return list<Invitation>
     */
    public function findByEmail(string $email): array;

    public function save(Invitation $entity): void;

    public function remove(Invitation $entity): void;
}
