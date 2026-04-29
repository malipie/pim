<?php

declare(strict_types=1);

namespace App\Identity\Domain\Repository;

interface UserRepositoryInterface
{
    public function findById(\Symfony\Component\Uid\Uuid $id): ?\App\Identity\Domain\Entity\User;

    public function findByEmail(string $email): ?\App\Identity\Domain\Entity\User;

    public function save(\App\Identity\Domain\Entity\User $entity): void;

    public function remove(\App\Identity\Domain\Entity\User $entity): void;
}
