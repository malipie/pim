<?php

declare(strict_types=1);

namespace App\Integration\Generic\Domain\Repository;

use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Entity\RemoteEndpoint;
use App\Integration\Generic\Domain\Enum\RemoteEndpointRole;
use Symfony\Component\Uid\Uuid;

interface RemoteEndpointRepositoryInterface
{
    public function save(RemoteEndpoint $endpoint): void;

    public function remove(RemoteEndpoint $endpoint): void;

    public function findById(Uuid $id): ?RemoteEndpoint;

    /**
     * @return list<RemoteEndpoint>
     */
    public function findByConnection(Connection $connection): array;

    public function findByConnectionAndRole(Connection $connection, RemoteEndpointRole $role): ?RemoteEndpoint;
}
