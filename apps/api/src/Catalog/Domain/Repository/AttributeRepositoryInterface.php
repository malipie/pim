<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Entity\Attribute;
use App\Shared\Domain\Tenant;
use Symfony\Component\Uid\Uuid;

interface AttributeRepositoryInterface
{
    public function findById(Uuid $id): ?Attribute;

    public function findByCode(string $code, Tenant $tenant): ?Attribute;

    public function save(Attribute $attribute): void;

    public function remove(Attribute $attribute): void;

    /**
     * VIEW-38 (#579) — distinct codes of every `is_filterable=true`
     * attribute across all tenants. Drives the union that builds the
     * Meilisearch `filterableAttributes` setting; the index is shared
     * (single-tenant deploy in MVP) so the union is exactly the list
     * Meili needs.
     *
     * @return list<string>
     */
    public function filterableCodes(): array;
}
