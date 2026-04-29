<?php

declare(strict_types=1);

namespace App\Channel\Domain\Repository;

interface LocaleRepositoryInterface
{
    public function findById(\Symfony\Component\Uid\Uuid $id): ?\App\Channel\Domain\Entity\Locale;

    public function findByCode(string $code): ?\App\Channel\Domain\Entity\Locale;

    public function save(\App\Channel\Domain\Entity\Locale $entity): void;

    public function remove(\App\Channel\Domain\Entity\Locale $entity): void;
}
