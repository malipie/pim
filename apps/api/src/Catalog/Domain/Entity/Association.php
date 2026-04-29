<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use App\Catalog\Infrastructure\Doctrine\Repository\AssociationRepository;
use App\Shared\Application\TenantScoped;
use App\Shared\Domain\Tenant;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use LogicException;
use Symfony\Component\Uid\Uuid;

/**
 * One-way relation between two {@see CatalogObject} rows under a given
 * {@see AssociationType} (e.g. SKU-X → SKU-Y as `cross_sell`).
 *
 * Per ADR-009 the link is generic across `kind` — products → categories,
 * categories → assets, products → products. Schema enforces:
 *
 *   - no self-loop (`source_object_id != target_object_id`) at the
 *     application layer; the migration adds a CHECK constraint;
 *   - one row per (source, target, type) triple via UNIQUE index with
 *     `NULLS NOT DISTINCT` (no nulls here, but the convention matches
 *     the rest of the catalog).
 *
 * Reverse direction is NOT auto-mirrored — admin UI / agent decides
 * whether (A→B cross_sell) implies (B→A cross_sell). Keeping rows
 * one-way leaves room for asymmetric semantics like "this product
 * replaces that one".
 */
#[ORM\Entity(repositoryClass: AssociationRepository::class)]
#[ORM\Table(name: 'object_associations')]
#[ORM\UniqueConstraint(name: 'object_associations_triple_uniq', columns: ['source_object_id', 'target_object_id', 'type_id'])]
#[ORM\Index(name: 'object_associations_tenant_idx', columns: ['tenant_id'])]
#[ORM\Index(name: 'object_associations_source_type_idx', columns: ['source_object_id', 'type_id'])]
#[ORM\Index(name: 'object_associations_target_idx', columns: ['target_object_id'])]
class Association implements TenantScoped
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Tenant::class)]
    #[ORM\JoinColumn(name: 'tenant_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private ?Tenant $tenant = null;

    #[ORM\ManyToOne(targetEntity: CatalogObject::class)]
    #[ORM\JoinColumn(name: 'source_object_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private CatalogObject $source;

    #[ORM\ManyToOne(targetEntity: CatalogObject::class)]
    #[ORM\JoinColumn(name: 'target_object_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private CatalogObject $target;

    #[ORM\ManyToOne(targetEntity: AssociationType::class)]
    #[ORM\JoinColumn(name: 'type_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private AssociationType $type;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $position;

    public function __construct(
        CatalogObject $source,
        CatalogObject $target,
        AssociationType $type,
        int $position = 0,
        ?Uuid $id = null,
    ) {
        if ($source->getId()->equals($target->getId())) {
            throw new LogicException('Association cannot connect an object to itself.');
        }

        $this->id = $id ?? Uuid::v7();
        $this->source = $source;
        $this->target = $target;
        $this->type = $type;
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

    public function getSource(): CatalogObject
    {
        return $this->source;
    }

    public function getTarget(): CatalogObject
    {
        return $this->target;
    }

    public function getType(): AssociationType
    {
        return $this->type;
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
