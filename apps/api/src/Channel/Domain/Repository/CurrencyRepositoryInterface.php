<?php

declare(strict_types=1);

namespace App\Channel\Domain\Repository;

interface CurrencyRepositoryInterface
{
    public function findById(\Symfony\Component\Uid\Uuid $id): ?\App\Channel\Domain\Entity\Currency;

    public function findByCode(string $code): ?\App\Channel\Domain\Entity\Currency;

    public function save(\App\Channel\Domain\Entity\Currency $entity): void;

    public function remove(\App\Channel\Domain\Entity\Currency $entity): void;
}
