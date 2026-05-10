<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use DateTimeImmutable;

/**
 * Junction declaring that a product (or other catalog object) is assigned to
 * a category, with at most one assignment marked as primary per product.
 *
 * Both `product` and `category` reference the same `objects` table (per
 * ADR-009 ObjectKind discriminates rows). The DB CHECK enforces
 * `object_id <> category_id` so a category cannot self-assign.
 *
 * `isPrimary=true` is constrained to at most one row per `product` by a
 * partial unique index — see `Version20260510221123` migration. Primary
 * is meaningful for downstream channel exports (e.g. Shopify wants one
 * category per product) and for breadcrumbs in the storefront.
 *
 * Walked by `EffectiveAttributeGroupResolver` (PCAT-03) to compute the
 * effective attribute groups for a product: for each assigned category
 * the resolver collects ancestor categories and merges their declared
 * `CategoryAttributeGroup`s on top of the product's ObjectType groups.
 *
 * No `tenant_id` column — tenant scope is inherited via `objects.tenant_id`
 * on both FKs (NOT NULL + TenantScoped). Listed in
 * `TenantAuditCommand::INFRA_TABLES` allowlist.
 */
class ObjectCategory
{
    private CatalogObject $product;
    private CatalogObject $category;
    private bool $isPrimary;
    private int $position;
    private DateTimeImmutable $createdAt;

    public function __construct(
        CatalogObject $product,
        CatalogObject $category,
        bool $isPrimary = false,
        int $position = 0,
        ?DateTimeImmutable $createdAt = null,
    ) {
        $this->product = $product;
        $this->category = $category;
        $this->isPrimary = $isPrimary;
        $this->position = $position;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
    }

    public function getProduct(): CatalogObject
    {
        return $this->product;
    }

    public function getCategory(): CatalogObject
    {
        return $this->category;
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function promoteToPrimary(): void
    {
        $this->isPrimary = true;
    }

    public function demoteFromPrimary(): void
    {
        $this->isPrimary = false;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function reorder(int $position): void
    {
        $this->position = $position;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
