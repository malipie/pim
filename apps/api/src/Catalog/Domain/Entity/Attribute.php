<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use App\Catalog\Domain\AttributeType;
use App\Shared\Application\TenantScoped;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use LogicException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A single tenant-scoped attribute definition.
 *
 * Attributes describe the shape of `Object` values (per ADR-009 generic
 * ObjectType model). One attribute can be reused across many ObjectTypes
 * via the `object_type_attributes` junction (added in #32) — e.g. `name`
 * on every kind, `seo_title` on `product` + `category`.
 *
 * `validation_rules` is plain JSONB in #31. The per-type interpreter that
 * enforces "min/max for `number`", "max_length for `text`", etc. lands in
 * #39 (0.3.9). Until then it is a structured payload the entity stores
 * verbatim — admins can already configure it; nothing on the server side
 * trips on out-of-range values yet.
 *
 * `group_id` FK is nullable + ON DELETE SET NULL — removing an attribute
 * group leaves its members ungrouped rather than deleting them.
 */
class Attribute implements TenantScoped
{
    private Uuid $id;
    private ?Tenant $tenant = null;
    private ?AttributeGroup $group = null;
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
    private ?array $help = null;
    private AttributeType $type;

    private bool $isLocalizable = false;

    private bool $isScopable = false;

    private bool $isRequired = false;

    /**
     * @var array<string, mixed>
     */
    private array $validationRules = [];

    private int $position = 0;
    private DateTimeImmutable $createdAt;

    /**
     * @param array<string, string> $label
     */
    public function __construct(
        string $code,
        array $label,
        AttributeType $type,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->code = $code;
        $this->label = $label;
        $this->type = $type;
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

    public function getGroup(): ?AttributeGroup
    {
        return $this->group;
    }

    public function setGroup(?AttributeGroup $group): void
    {
        $this->group = $group;
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
    public function setLabel(array $label): void
    {
        $this->label = $label;
    }

    /**
     * @return array<string, string>|null
     */
    public function getHelp(): ?array
    {
        return $this->help;
    }

    /**
     * @param array<string, string>|null $help
     */
    public function setHelp(?array $help): void
    {
        $this->help = $help;
    }

    public function getType(): AttributeType
    {
        return $this->type;
    }

    public function isLocalizable(): bool
    {
        return $this->isLocalizable;
    }

    public function setLocalizable(bool $localizable): void
    {
        $this->isLocalizable = $localizable;
    }

    public function isScopable(): bool
    {
        return $this->isScopable;
    }

    public function setScopable(bool $scopable): void
    {
        $this->isScopable = $scopable;
    }

    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    public function setRequired(bool $required): void
    {
        $this->isRequired = $required;
    }

    /**
     * @return array<string, mixed>
     */
    public function getValidationRules(): array
    {
        return $this->validationRules;
    }

    /**
     * @param array<string, mixed> $rules
     */
    public function setValidationRules(array $rules): void
    {
        $this->validationRules = $rules;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function usesOptions(): bool
    {
        return $this->type->usesOptions();
    }
}
