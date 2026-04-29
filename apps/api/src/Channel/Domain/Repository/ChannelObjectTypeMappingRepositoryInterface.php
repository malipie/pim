<?php

declare(strict_types=1);

namespace App\Channel\Domain\Repository;

interface ChannelObjectTypeMappingRepositoryInterface
{
    public function findById(\Symfony\Component\Uid\Uuid $id): ?\App\Channel\Domain\Entity\ChannelObjectTypeMapping;

    /**
     * @return list<\App\Channel\Domain\Entity\ChannelObjectTypeMapping>
     */
    public function findByChannelAndObjectType(\App\Channel\Domain\Entity\Channel $channel, \App\Catalog\Domain\Entity\ObjectType $objectType): array;

    public function findOne(\App\Channel\Domain\Entity\Channel $channel, \App\Catalog\Domain\Entity\ObjectType $objectType, \App\Catalog\Domain\Entity\Attribute $attribute): ?\App\Channel\Domain\Entity\ChannelObjectTypeMapping;

    public function save(\App\Channel\Domain\Entity\ChannelObjectTypeMapping $entity): void;

    public function remove(\App\Channel\Domain\Entity\ChannelObjectTypeMapping $entity): void;
}
