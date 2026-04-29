<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Entity\Association;
use App\Catalog\Domain\Entity\AssociationType;
use App\Catalog\Domain\Entity\CatalogObject;
use Symfony\Component\Uid\Uuid;

interface AssociationRepositoryInterface
{
    public function findById(Uuid $id): ?Association;

    /**
     * @return list<Association>
     */
    public function findAssociations(CatalogObject $source, ?AssociationType $type = null): array;

    public function findOne(CatalogObject $source, CatalogObject $target, AssociationType $type): ?Association;

    public function save(Association $association): void;

    public function remove(Association $association): void;
}
