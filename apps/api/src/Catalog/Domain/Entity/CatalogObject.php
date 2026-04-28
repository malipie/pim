<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use App\Catalog\Domain\ObjectKind;
use App\Catalog\Infrastructure\Doctrine\Repository\CatalogObjectRepository;
use App\Identity\Application\TenantScoped;
use App\Identity\Domain\Entity\Tenant;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
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
#[ORM\Entity(repositoryClass: CatalogObjectRepository::class)]
#[ORM\Table(name: 'objects')]
#[ORM\UniqueConstraint(name: 'objects_tenant_kind_code_uniq', columns: ['tenant_id', 'kind', 'code'])]
#[ORM\Index(name: 'objects_tenant_type_idx', columns: ['tenant_id', 'object_type_id'])]
#[ORM\Index(name: 'objects_tenant_kind_idx', columns: ['tenant_id', 'kind'])]
class CatalogObject implements TenantScoped
{
    public const string STATUS_DRAFT = 'draft';
    public const string STATUS_PUBLISHED = 'published';
    public const string STATUS_ARCHIVED = 'archived';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(name: 'tenant_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private ?Tenant $tenant = null;

    #[ORM\ManyToOne(targetEntity: ObjectType::class)]
    #[ORM\JoinColumn(name: 'object_type_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private ObjectType $objectType;

    #[ORM\Column(type: Types::STRING, length: 32, enumType: ObjectKind::class)]
    private ObjectKind $kind;

    #[ORM\Column(type: 'string', length: 128)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 128)]
    private string $code;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?self $parent = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $enabled = true;

    #[ORM\Column(type: 'string', length: 16, options: ['default' => self::STATUS_DRAFT])]
    private string $status = self::STATUS_DRAFT;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true, 'default' => '{}'])]
    private array $completeness = [];

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(name: 'attributes_indexed', type: Types::JSON, options: ['jsonb' => true, 'default' => '{}'])]
    private array $attributesIndexed = [];

    /**
     * Postgres LTREE column. Doctrine has no native ltree type — we map
     * it as a plain string (the Doctrine listener in #33 validates
     * format). Nullable; only `kind='category'` rows carry a value.
     */
    #[ORM\Column(type: Types::STRING, length: 4096, nullable: true)]
    private ?string $path = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
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

    public function setParent(?self $parent): void
    {
        $this->parent = $parent;
        $this->touch();
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
        $this->touch();
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
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
    public function setCompleteness(array $completeness): void
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
    public function setAttributesIndexed(array $attributes): void
    {
        $this->attributesIndexed = $attributes;
        $this->touch();
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): void
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
