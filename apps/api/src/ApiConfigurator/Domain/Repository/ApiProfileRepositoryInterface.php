<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Domain\Repository;

use App\ApiConfigurator\Domain\Entity\ApiProfile;
use Symfony\Component\Uid\Uuid;

interface ApiProfileRepositoryInterface
{
    public function save(ApiProfile $profile): void;

    public function remove(ApiProfile $profile): void;

    public function findById(Uuid $id): ?ApiProfile;

    public function findByCode(string $code): ?ApiProfile;
}
