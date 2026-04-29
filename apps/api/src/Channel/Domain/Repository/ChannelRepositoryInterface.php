<?php

declare(strict_types=1);

namespace App\Channel\Domain\Repository;

interface ChannelRepositoryInterface
{
    public function findById(\Symfony\Component\Uid\Uuid $id): ?\App\Channel\Domain\Entity\Channel;

    public function findByCode(string $code, \App\Shared\Domain\Tenant $tenant): ?\App\Channel\Domain\Entity\Channel;

    public function save(\App\Channel\Domain\Entity\Channel $entity): void;

    public function remove(\App\Channel\Domain\Entity\Channel $entity): void;
}
