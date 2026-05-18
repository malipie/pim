<?php

declare(strict_types=1);

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Entity\PasswordResetToken;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

interface PasswordResetTokenRepositoryInterface
{
    public function findById(Uuid $id): ?PasswordResetToken;

    public function findByHash(string $tokenHash): ?PasswordResetToken;

    public function save(PasswordResetToken $entity): void;

    public function remove(PasswordResetToken $entity): void;

    /**
     * Purge expired/used rows older than $cutoff. Returns affected count.
     */
    public function purgeStale(DateTimeImmutable $cutoff): int;
}
