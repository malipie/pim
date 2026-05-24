<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use App\Shared\Application\TenantScoped;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use LogicException;
use Symfony\Component\Uid\Uuid;

/**
 * ADR-014 / MOD-02 (#894) — one-way link between two {@see CatalogObject}
 * rows carried by an Attribute of type `relation`.
 *
 * Replaces the legacy {@see Association} + {@see AssociationType} pair.
 * The link is qualified by an `Attribute` (not a separate `AssociationType`
 * row): every "kind of relation" is now a seeded or user-defined attribute
 * with `type = relation` plus the three config columns shipped in MOD-01
 * (`relation_target_object_type_ids`, `relation_cardinality`,
 * `relation_advanced`).
 *
 * Schema invariants:
 * - no self-loop: `source.id != target.id` enforced both here and via the
 *   `object_relations_no_self_loop_chk` CHECK constraint;
 * - unique triple `(source, target, attribute)` — UNIQUE index in the
 *   migration prevents accidental dupes from the CRUD endpoint (MOD-06);
 * - reverse direction is NOT auto-mirrored; reverse view is a read-only
 *   query (`MOD-07`).
 *
 * `metadata` is reserved for advanced relations (per-link fields landing
 * in MOD-08). Non-advanced links carry the default empty object — no
 * separate schema until MOD-08 ships.
 */
class ObjectRelation implements TenantScoped
{
    private Uuid $id;
    private ?Tenant $tenant = null;
    private CatalogObject $source;
    private CatalogObject $target;
    private Attribute $attribute;
    private int $position;

    /**
     * @var array<string, mixed>
     */
    private array $metadata;
    private DateTimeImmutable $createdAt;

    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        CatalogObject $source,
        CatalogObject $target,
        Attribute $attribute,
        int $position = 0,
        array $metadata = [],
        ?Uuid $id = null,
    ) {
        if ($source->getId()->equals($target->getId())) {
            throw new LogicException('ObjectRelation cannot connect an object to itself.');
        }

        $this->id = $id ?? Uuid::v7();
        $this->source = $source;
        $this->target = $target;
        $this->attribute = $attribute;
        $this->position = $position;
        $this->metadata = $metadata;
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

    public function getSource(): CatalogObject
    {
        return $this->source;
    }

    public function getTarget(): CatalogObject
    {
        return $this->target;
    }

    public function getAttribute(): Attribute
    {
        return $this->attribute;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function reorder(int $position): void
    {
        $this->position = $position;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function updateMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
