<?php

declare(strict_types=1);

namespace App\Asset\Domain\Repository;

interface AssetRepositoryInterface
{
    public function findById(\Symfony\Component\Uid\Uuid $id): ?\App\Asset\Domain\Entity\Asset;

    public function findByCode(string $code, \App\Shared\Domain\Tenant $tenant): ?\App\Asset\Domain\Entity\Asset;

    public function findByContentHash(string $contentHash, \App\Shared\Domain\Tenant $tenant): ?\App\Asset\Domain\Entity\Asset;

    public function findByObjectId(\Symfony\Component\Uid\Uuid $objectId): ?\App\Asset\Domain\Entity\Asset;

    /**
     * Per-chunk existence prefetch for the importer (IMP2-1.8 galleries):
     * returns the subset of the given RFC 4122 ids that exist for the tenant.
     * One query for the whole chunk instead of a findById per cell.
     *
     * @param list<string> $rfc4122Ids
     *
     * @return list<string> existing ids, RFC 4122
     */
    public function existingIds(array $rfc4122Ids, \App\Shared\Domain\Tenant $tenant): array;

    public function save(\App\Asset\Domain\Entity\Asset $entity): void;

    public function remove(\App\Asset\Domain\Entity\Asset $entity): void;
}
