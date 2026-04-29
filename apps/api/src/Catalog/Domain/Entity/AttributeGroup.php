<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use App\Catalog\Infrastructure\Doctrine\Repository\AttributeGroupRepository;
use App\Shared\Application\TenantScoped;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use LogicException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Container that groups attributes for admin UX (e.g. "SEO", "Logistics",
 * "Marketing"). A group is purely organisational — it carries a localised
 * label + an ordering position and lives entirely within one tenant.
 *
 * Attributes either belong to one group or stay ungrouped (`group_id` NULL
 * on Attribute). Removing a group leaves its attributes alive but ungrouped
 * (FK ON DELETE SET NULL) so admins do not lose data by re-organising.
 *
 * `label` is JSONB `{pl: "...", en: "..."}` so the same row carries every
 * supported locale in MVP — no separate translations table.
 */
#[ORM\Entity(repositoryClass: AttributeGroupRepository::class)]
#[ORM\Table(name: 'attribute_groups')]
#[ORM\UniqueConstraint(name: 'attribute_groups_tenant_code_uniq', columns: ['tenant_id', 'code'])]
#[ORM\Index(name: 'attribute_groups_tenant_position_idx', columns: ['tenant_id', 'position'])]
class AttributeGroup implements TenantScoped
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(name: 'tenant_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private ?Tenant $tenant = null;

    #[ORM\Column(type: 'string', length: 64)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    private string $code;

    /**
     * @var array<string, string>
     */
    #[ORM\Column(type: Types::JSON, options: ['jsonb' => true])]
    #[Assert\Type('array')]
    private array $label;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $position;

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $createdAt;

    /**
     * @param array<string, string> $label
     */
    public function __construct(
        string $code,
        array $label,
        int $position = 0,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->code = $code;
        $this->label = $label;
        $this->position = $position;
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
    public function setLabel(array $label): void
    {
        $this->label = $label;
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
}
