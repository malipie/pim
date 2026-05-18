<?php

declare(strict_types=1);

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Entity\SuperAdmin;
use Symfony\Component\Uid\Uuid;

interface SuperAdminRepositoryInterface
{
    public function findById(Uuid $id): ?SuperAdmin;

    public function findByEmail(string $email): ?SuperAdmin;

    public function save(SuperAdmin $entity): void;

    public function remove(SuperAdmin $entity): void;
}
