<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use App\Catalog\Domain\ObjectKind;
use App\Shared\Application\TenantScoped;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use LogicException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Generic catalog object — polymorphic per `kind` (per ADR-009).
 *
 * One table for every domain entity in PIM: products, categories, assets,
 * and (phase 2/3) custom kinds. Sugar API paths in #41 expose the same
 * row as `/api/products`, `/api/categories`, `/api/assets` for DX.
 *
 * Class name is `CatalogObject` (not `Object`) because `Object` is
 * effectively a reserved keyword in PHP (cannot use it as a class name
 * since 7.2). The Doctrine table is still `objects`.
 *
 * `kind` is denormalised from `object_type_id → object_types.kind` to
 * cheapen WHERE clauses on the hot read path. A Doctrine listener (in
 * #33 / #38) keeps the two in sync; admins never set `kind` directly.
 *
 * `attributes_indexed JSONB` is the denormalised cache of every
 * ObjectValue for this row, keyed by attribute code (`{name: {pl: '…',
 * en: '…'}, sku: '…', color: 'red'}`). The GIN index lets the search
 * layer (#52) answer `attributes_indexed @> '{"color": "red"}'` in
 * sub-50ms for 10k×200×3 dataset (DoD benchmark of #34).
 *
 * `path LTREE` is nullable — only `kind='category'` rows carry it.
 * The `kind = 'category' OR path IS NULL` invariant + partial indexes
 * land in #33 along with the validator listener; this migration just
 * adds the column so #33 can constrain it without an ALTER.
 *
 * `parent_id` is the self-FK used by:
 *   - `kind='product'` for variants (size/color of a parent SKU);
 *   - `kind='category'` for the tree (parent category in ltree).
 */
class CatalogObject implements TenantScoped
{
    public const string STATUS_DRAFT = 'draft';
    public const string STATUS_PUBLISHED = 'published';
    public const string STATUS_ARCHIVED = 'archived';
    private Uuid $id;
    private ?Tenant $tenant = null;
    private ObjectType $objectType;
    private ObjectKind $kind;
    #[Assert\NotBlank]
    #[Assert\Length(max: 128)]
    private string $code;
    private ?self $parent = null;

    private bool $enabled = true;

    private string $status = self::STATUS_DRAFT;

    /**
     * @var array<string, mixed>
     */
    private array $completeness = [];

    /**
     * @var array<string, mixed>
     */
    private array $attributesIndexed = [];

    /**
     * Postgres LTREE column (`#33`). Custom Doctrine type
     * {@see \App\Catalog\Infrastructure\Doctrine\Type\LtreeType} maps it
     * as `?string` on the PHP side. Nullable + a CHECK constraint on the
     * database pins "path is for `kind='category'` only"; the
     * {@see \App\Catalog\Infrastructure\Doctrine\EventListener\CategoryPathValidator}
     * enforces the same invariant on writes with a friendlier error
     * message and validates ltree label format.
     */
    private ?string $path = null;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    public function __construct(
        ObjectType $objectType,
        string $code,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->objectType = $objectType;
        $this->kind = $objectType->getKind();
        $this->code = $code;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    /**
     * @internal stamped by TenantAssignmentListener on prePersist
     */
    public function assignTenant(Tenant $tenant): void
    {
        if (null !== $this->tenant) {
            throw new LogicException('Tenant is already assigned and cannot be reassigned.');
        }

        $this->tenant = $tenant;
    }

    public function getObjectType(): ObjectType
    {
        return $this->objectType;
    }

    public function getKind(): ObjectKind
    {
        return $this->kind;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function assignParent(?self $parent): void
    {
        $this->parent = $parent;
        $this->touch();
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function changeEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
        $this->touch();
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function transitionTo(string $status): void
    {
        $this->status = $status;
        $this->touch();
    }

    /**
     * @return array<string, mixed>
     */
    public function getCompleteness(): array
    {
        return $this->completeness;
    }

    /**
     * @param array<string, mixed> $completeness
     */
    public function recordCompleteness(array $completeness): void
    {
        $this->completeness = $completeness;
        $this->touch();
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttributesIndexed(): array
    {
        return $this->attributesIndexed;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function updateAttributeIndex(array $attributes): void
    {
        $this->attributesIndexed = $attributes;
        $this->touch();
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function attachToPath(?string $path): void
    {
        $this->path = $path;
        $this->touch();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
