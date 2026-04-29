<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use App\Shared\Application\TenantScoped;
use App\Shared\Domain\Tenant;
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
class AttributeOption implements TenantScoped
{
    private Uuid $id;
    private ?Tenant $tenant = null;
    private Attribute $attribute;
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    private string $code;

    /**
     * @var array<string, string>
     */
    #[Assert\Type('array')]
    private array $label;

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
