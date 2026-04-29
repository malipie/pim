<?php

declare(strict_types=1);

namespace App\Asset\Domain\Repository;

interface AssetVariantRepositoryInterface
{
    public function findById(\Symfony\Component\Uid\Uuid $id): ?\App\Asset\Domain\Entity\AssetVariant;

    /**
     * @return list<\App\Asset\Domain\Entity\AssetVariant>
     */
    public function findByAsset(\App\Asset\Domain\Entity\Asset $asset): array;

    public function save(\App\Asset\Domain\Entity\AssetVariant $entity): void;

    public function remove(\App\Asset\Domain\Entity\AssetVariant $entity): void;
}
