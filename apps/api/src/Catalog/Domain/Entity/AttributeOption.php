<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use App\Catalog\Infrastructure\Doctrine\Repository\AttributeOptionRepository;
use App\Shared\Application\TenantScoped;
use App\Shared\Domain\Tenant;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use LogicException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One choice for `select` / `multiselect` Attribute. Other types do not
 * carry options — the invariant is enforced at the validator layer (#39),
 * not at the schema, because Postgres partial unique on (attribute_id,
 * code) only when type=select/multiselect would force a join in every
 * insert path.
 *
 * Tenant_id is denormalised onto AttributeOption (rather than joined via
 * the parent Attribute) so {@see TenantFilter} can scope option queries
 * without a JOIN, and so `pim:tenant:audit` sees the table as domain.
 * Cost: 16 extra bytes per row, plus listener stamping.
 */
#[ORM\Entity(repositoryClass: AttributeOptionRepository::class)]
#[ORM\Table(name: 'attribute_options')]
#[ORM\UniqueConstraint(name: 'attribute_options_attribute_code_uniq', columns: ['attribute_id', 'code'])]
#[ORM\Index(name: 'attribute_options_attribute_position_idx', columns: ['attribute_id', 'position'])]
#[ORM\Index(name: 'attribute_options_tenant_idx', columns: ['tenant_id'])]
class AttributeOption implements TenantScoped
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(name: 'tenant_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private ?Tenant $tenant = null;

    #[ORM\ManyToOne(targetEntity: Attribute::class)]
    #[ORM\JoinColumn(name: 'attribute_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Attribute $attribute;

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

    /**
     * @param array<string, string> $label
     */
    public function __construct(
        Attribute $attribute,
        string $code,
        array $label,
        int $position = 0,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->attribute = $attribute;
        $this->code = $code;
        $this->label = $label;
        $this->position = $position;
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

    public function getAttribute(): Attribute
    {
        return $this->attribute;
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
}
