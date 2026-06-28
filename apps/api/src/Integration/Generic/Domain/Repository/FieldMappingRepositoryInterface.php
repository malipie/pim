<?php

declare(strict_types=1);

namespace App\Integration\Generic\Domain\Repository;

use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Entity\FieldMapping;
use Symfony\Component\Uid\Uuid;

interface FieldMappingRepositoryInterface
{
    public function save(FieldMapping $mapping): void;

    public function remove(FieldMapping $mapping): void;

    public function findById(Uuid $id): ?FieldMapping;

    /**
     * @return list<FieldMapping>
     */
    public function findByConnection(Connection $connection): array;

    public function findByConnectionAndTarget(Connection $connection, string $pimTarget): ?FieldMapping;
}
