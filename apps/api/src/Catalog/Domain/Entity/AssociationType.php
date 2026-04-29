<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use App\Shared\Application\TenantScoped;
use App\Shared\Domain\Tenant;
use LogicException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Tenant-defined classification for {@see Association} rows
 * (cross-sell, up-sell, related, accessories, …).
 *
 * Four default types — `cross_sell`, `up_sell`, `related`, `accessories` —
 * are seeded per tenant by {@see \App\Catalog\Application\BuiltInAssociationTypeSeeder}.
 * Tenants can add their own custom types (e.g. `bundles`, `replacements`)
 * through the admin UI in epic 0.6 — there is no `is_built_in` flag here
 * because the contract is "all rows are tenant-defined", whereas the
 * built-ins are merely the seed every tenant starts with.
 *
 * Per ADR-009 the association is between any two `CatalogObject` rows
 * (not just products) — products linking to categories, categories
 * linking to assets, etc.
 */
class AssociationType implements TenantScoped
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

    private int $position;

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
}
