<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use App\Catalog\Domain\ObjectKind;
use App\Catalog\Infrastructure\Doctrine\Repository\ObjectTypeRepository;
use App\Shared\Application\TenantScoped;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use LogicException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Generic template for every domain object kind in PIM (per ADR-009).
 *
 * Predefined kinds (`Product`, `Category`, `Asset`) seed as fixtures with
 * `is_built_in=true` in #33 — they are platform-owned and protected from
 * deletion by `ObjectTypeService::delete`. Custom kinds (`Customer`,
 * `Supplier`, `PriceList`, etc.) are unlocked in phase 2/3 via the
 * `enable_custom_object_types` feature flag.
 *
 * `label_attribute_id` and `image_attribute_id` are nullable foreign keys
 * to `attributes` — the convention is "name" → display label, "main_image"
 * → image. Cross-ObjectType reference is technically possible but should
 * be paired with the junction; a validator that enforces this lands as a
 * follow-up after #34.
 *
 * `completeness_rules` is a JSONB payload stored verbatim. The Doctrine
 * listener that interprets it (e.g. `{required: ['sku', 'name'], weight:
 * {sku: 2, name: 1}}`) and computes `Object.completeness_pct` is in #38.
 *
 * `schema_version` is a forward hook for export/import tooling planned
 * for phase 2 (when enterprise customers move definitions between
 * environments) — set on every save, bumped manually when a schema-
 * breaking change to `completeness_rules` ships.
 */
#[ORM\Entity(repositoryClass: ObjectTypeRepository::class)]
#[ORM\Table(name: 'object_types')]
#[ORM\UniqueConstraint(name: 'object_types_tenant_code_uniq', columns: ['tenant_id', 'code'])]
#[ORM\Index(name: 'object_types_tenant_kind_idx', columns: ['tenant_id', 'kind'])]
class ObjectType implements TenantScoped
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(name: 'tenant_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private ?Tenant $tenant = null;

    #[ORM\Column(type: 'string', length: 128)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 128)]
    private string $code;

    #[ORM\Column(type: Types::STRING, length: 32, enumType: ObjectKind::class)]
    private ObjectKind $kind;

    #[ORM\Column(name: 'is_built_in', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isBuiltIn = false;

    /**
     * @var array<string, string>
     */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    #[Assert\Type('array')]
    private array $label;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(name: 'completeness_rules', type: Types::JSON, options: ['jsonb' => true, 'default' => '{}'])]
    private array $completenessRules = [];

    #[ORM\ManyToOne(targetEntity: Attribute::class)]
    #[ORM\JoinColumn(name: 'label_attribute_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Attribute $labelAttribute = null;

    #[ORM\ManyToOne(targetEntity: Attribute::class)]
    #[ORM\JoinColumn(name: 'image_attribute_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Attribute $imageAttribute = null;

    #[ORM\Column(name: 'schema_version', type: Types::INTEGER, options: ['default' => 1])]
    private int $schemaVersion = 1;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $updatedAt;

    /**
     * @param array<string, string> $label
     */
    public function __construct(
        string $code,
        ObjectKind $kind,
        array $label,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->code = $code;
        $this->kind = $kind;
        $this->label = $label;
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

    public function getCode(): string
    {
        return $this->code;
    }

    public function getKind(): ObjectKind
    {
        return $this->kind;
    }

    public function isBuiltIn(): bool
    {
        return $this->isBuiltIn;
    }

    public function markBuiltIn(): void
    {
        $this->isBuiltIn = true;
        $this->touch();
    }

    /**
     * @return array<string, string>
     */
    public function getLabel(): array
    {
        return $this->label;
    }

    /**
     * @param array<string, string> $label
     */
    public function setLabel(array $label): void
    {
        $this->label = $label;
        $this->touch();
    }

    /**
     * @return array<string, mixed>
     */
    public function getCompletenessRules(): array
    {
        return $this->completenessRules;
    }

    /**
     * @param array<string, mixed> $rules
     */
    public function setCompletenessRules(array $rules): void
    {
        $this->completenessRules = $rules;
        $this->touch();
    }

    public function getLabelAttribute(): ?Attribute
    {
        return $this->labelAttribute;
    }

    public function setLabelAttribute(?Attribute $attribute): void
    {
        $this->labelAttribute = $attribute;
        $this->touch();
    }

    public function getImageAttribute(): ?Attribute
    {
        return $this->imageAttribute;
    }

    public function setImageAttribute(?Attribute $attribute): void
    {
        $this->imageAttribute = $attribute;
        $this->touch();
    }

    public function getSchemaVersion(): int
    {
        return $this->schemaVersion;
    }

    public function bumpSchemaVersion(): void
    {
        ++$this->schemaVersion;
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
