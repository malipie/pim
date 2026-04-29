<?php

declare(strict_types=1);

namespace App\Asset\Domain\Repository;

interface AssetRepositoryInterface
{
    public function findById(\Symfony\Component\Uid\Uuid $id): ?\App\Asset\Domain\Entity\Asset;

    public function findByCode(string $code, \App\Shared\Domain\Tenant $tenant): ?\App\Asset\Domain\Entity\Asset;

    public function save(\App\Asset\Domain\Entity\Asset $entity): void;

    public function remove(\App\Asset\Domain\Entity\Asset $entity): void;
}
