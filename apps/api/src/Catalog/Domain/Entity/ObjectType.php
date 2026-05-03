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
class ObjectType implements TenantScoped
{
    private Uuid $id;
    private ?Tenant $tenant = null;
    #[Assert\NotBlank]
    #[Assert\Length(max: 128)]
    private string $code;
    private ObjectKind $kind;

    private bool $isBuiltIn = false;
    private bool $codeImmutable = false;
    private bool $deletable = true;

    #[Assert\Length(max: 64)]
    private ?string $icon = null;

    #[Assert\Length(max: 16)]
    private ?string $color = null;

    /**
     * @var array<string, string>
     */
    #[Assert\Type('array')]
    private array $label;

    /**
     * @var array<string, mixed>
     */
    private array $completenessRules = [];
    private ?Attribute $labelAttribute = null;
    private ?Attribute $imageAttribute = null;

    /**
     * Configurable behavior toggles surfaced in the modeling UI per VIEW-01
     * (#372). Default `false`; built-in seeders set `hasVariants` for product
     * and `hierarchical` for category. Built-in rows have these fields locked
     * by the voter, custom rows expose them in the Settings card.
     */
    private bool $hierarchical = false;
    private bool $hasVariants = false;
    private bool $abstract = false;

    /**
     * VIEW-01c (#414) — sidebar surface controls. `displayInMenu=true` makes
     * the type render as its own primary nav entry; `menuPosition` orders it
     * (ascending). Built-ins ship with both flags set by the seeder so the
     * default sidebar matches the existing layout. Unlike the other Settings
     * toggles, these two are NOT domain invariants — built-in rows can
     * change them freely (UX preference, not platform contract).
     */
    private bool $displayInMenu = false;
    private int $menuPosition = 0;

    /**
     * UUID list of ObjectTypes allowed as parent. Plain JSONB list (not a
     * junction) — N stays small (≤ 5 typical), and the only consumer is the
     * detail view's Allowed parent types chip strip. A junction would cost
     * us a roundtrip per detail page load for no gain at this cardinality.
     *
     * @var list<string>
     */
    private array $allowedParentTypeIds = [];

    private int $schemaVersion = 1;
    private DateTimeImmutable $createdAt;
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

    public function isCodeImmutable(): bool
    {
        return $this->codeImmutable;
    }

    public function lockCode(): void
    {
        $this->codeImmutable = true;
        $this->touch();
    }

    public function isDeletable(): bool
    {
        return $this->deletable;
    }

    public function markUndeletable(): void
    {
        $this->deletable = false;
        $this->touch();
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): void
    {
        $this->icon = $icon;
        $this->touch();
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): void
    {
        $this->color = $color;
        $this->touch();
    }

    /**
     * @throws LogicException when code_immutable is set
     */
    public function changeCode(string $code): void
    {
        if ($this->codeImmutable) {
            throw new LogicException('ObjectType code is immutable for this row.');
        }

        $this->code = $code;
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
    public function rename(array $label): void
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
    public function updateCompletenessRules(array $rules): void
    {
        $this->completenessRules = $rules;
        $this->touch();
    }

    public function getLabelAttribute(): ?Attribute
    {
        return $this->labelAttribute;
    }

    public function assignLabelAttribute(?Attribute $attribute): void
    {
        $this->labelAttribute = $attribute;
        $this->touch();
    }

    public function getImageAttribute(): ?Attribute
    {
        return $this->imageAttribute;
    }

    public function assignImageAttribute(?Attribute $attribute): void
    {
        $this->imageAttribute = $attribute;
        $this->touch();
    }

    public function isHierarchical(): bool
    {
        return $this->hierarchical;
    }

    public function setHierarchical(bool $value): void
    {
        $this->hierarchical = $value;
        $this->touch();
    }

    public function hasVariants(): bool
    {
        return $this->hasVariants;
    }

    /**
     * Symfony PropertyAccess accessor alias — `hasVariants()` reads naturally
     * in domain code but PropertyAccessor expects `getHasVariants()` /
     * `isHasVariants()` to expose it as the `hasVariants` property in the
     * normalized output.
     */
    public function getHasVariants(): bool
    {
        return $this->hasVariants;
    }

    public function setHasVariants(bool $value): void
    {
        $this->hasVariants = $value;
        $this->touch();
    }

    public function isAbstract(): bool
    {
        return $this->abstract;
    }

    public function setAbstract(bool $value): void
    {
        $this->abstract = $value;
        $this->touch();
    }

    public function isDisplayInMenu(): bool
    {
        return $this->displayInMenu;
    }

    /**
     * Symfony PropertyAccess accessor alias — surface as `displayInMenu`
     * property in normalized output (mirrors `getHasVariants`).
     */
    public function getDisplayInMenu(): bool
    {
        return $this->displayInMenu;
    }

    public function setDisplayInMenu(bool $value): void
    {
        $this->displayInMenu = $value;
        $this->touch();
    }

    public function getMenuPosition(): int
    {
        return $this->menuPosition;
    }

    public function setMenuPosition(int $value): void
    {
        $this->menuPosition = $value;
        $this->touch();
    }

    /**
     * @return list<string>
     */
    public function getAllowedParentTypeIds(): array
    {
        return $this->allowedParentTypeIds;
    }

    /**
     * @param list<string> $ids
     */
    public function setAllowedParentTypeIds(array $ids): void
    {
        $this->allowedParentTypeIds = array_values(array_unique($ids));
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
