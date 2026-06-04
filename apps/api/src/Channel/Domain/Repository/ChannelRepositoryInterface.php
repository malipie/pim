<?php

declare(strict_types=1);

namespace App\Channel\Domain\Repository;

interface ChannelRepositoryInterface
{
    public function findById(\Symfony\Component\Uid\Uuid $id): ?\App\Channel\Domain\Entity\Channel;

    public function findByCode(string $code, \App\Shared\Domain\Tenant $tenant): ?\App\Channel\Domain\Entity\Channel;

    /**
     * All channels for the tenant (Channel has no `isActive` flag yet, so
     * "all" == every channel). Used to enumerate per-channel completeness
     * scopes (#1152).
     *
     * @return list<\App\Channel\Domain\Entity\Channel>
     */
    public function findAllForTenant(\App\Shared\Domain\Tenant $tenant): array;

    public function save(\App\Channel\Domain\Entity\Channel $entity): void;

    public function remove(\App\Channel\Domain\Entity\Channel $entity): void;
}
