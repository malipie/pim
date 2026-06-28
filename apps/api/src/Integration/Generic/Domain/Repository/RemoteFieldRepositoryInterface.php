<?php

declare(strict_types=1);

namespace App\Integration\Generic\Domain\Repository;

use App\Integration\Generic\Domain\Entity\RemoteEndpoint;
use App\Integration\Generic\Domain\Entity\RemoteField;
use Symfony\Component\Uid\Uuid;

interface RemoteFieldRepositoryInterface
{
    public function save(RemoteField $field): void;

    public function remove(RemoteField $field): void;

    public function findById(Uuid $id): ?RemoteField;

    /**
     * @return list<RemoteField>
     */
    public function findByEndpoint(RemoteEndpoint $endpoint): array;
}
