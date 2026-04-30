<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use App\Shared\Application\TenantScoped;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use LogicException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Container that groups attributes for admin UX (e.g. "SEO", "Logistics",
 * "Marketing"). After ADR-012 a group is a first-class entity with its own
 * URL / audit / icon / colour, used as the unit of attachment to ObjectType
 * (global) and Category (inherited down the tree).
 *
 * `label` is JSONB `{pl: "...", en: "..."}` so the same row carries every
 * supported locale — no separate translations table. `description` follows
 * the same pattern.
 *
 * Two attachment paths to attributes coexist during the UI-08 migration:
 *  - legacy 1:N `Attribute.group_id` (still alive, written by 0.3.X seeders),
 *  - first-class M:N via `attribute_group_attributes` junction (ADR-012).
 *
 * `is_system_group=true` marks groups managed by the system (e.g. the auto-
 * attached "Audit" group #UI-08.3) — not deletable, not detachable from
 * ObjectType, code is immutable. `auto_attached=true` means the group is
 * automatically wired to every new ObjectType (currently only Audit).
 */
class AttributeGroup implements TenantScoped
{
    private Uuid $id;
    private ?Tenant $tenant = null;
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    private string $code;

    /**
     * @var array<string, string>
     */
    #[Assert\Type('array')]
    private array $label;

    /**
     * @var array<string, string>|null
     */
    #[Assert\Type('array')]
    private ?array $description = null;

    #[Assert\Length(max: 64)]
    private ?string $icon = null;

    #[Assert\Length(max: 16)]
    private ?string $color = null;

    private bool $isSystemGroup = false;
    private bool $autoAttached = false;

    private int $position;
    private DateTimeImmutable $createdAt;

    /**
     * @param array<string, string>      $label
     * @param array<string, string>|null $description
     */
    public function __construct(
        string $code,
        array $label,
        int $position = 0,
        ?Uuid $id = null,
        ?array $description = null,
        ?string $icon = null,
        ?string $color = null,
        bool $isSystemGroup = false,
        bool $autoAttached = false,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->code = $code;
        $this->label = $label;
        $this->position = $position;
        $this->description = $description;
        $this->icon = $icon;
        $this->color = $color;
        $this->isSystemGroup = $isSystemGroup;
        $this->autoAttached = $autoAttached;
        $this->createdAt = new DateTimeImmutable();
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

    /**
     * @return array<string, string>|null
     */
    public function getDescription(): ?array
    {
        return $this->description;
    }

    /**
     * @param array<string, string>|null $description
     */
    public function describe(?array $description): void
    {
        $this->description = $description;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): void
    {
        $this->icon = $icon;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): void
    {
        $this->color = $color;
    }

    public function isSystemGroup(): bool
    {
        return $this->isSystemGroup;
    }

    public function isAutoAttached(): bool
    {
        return $this->autoAttached;
    }
}
