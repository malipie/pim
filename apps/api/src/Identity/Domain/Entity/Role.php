<?php

declare(strict_types=1);

namespace App\Identity\Domain\Entity;

use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Uid\Uuid;

/**
 * RBAC role aggregating a set of permissions.
 *
 * `tenant` NULL means the role is global (built-in seeder roles in #27 —
 * super_admin / catalog_manager / integration_manager / viewer). Per-tenant
 * custom roles land in Faza 2+ once admin UI for role management arrives.
 */
class Role
{
    private Uuid $id;

    private ?Tenant $tenant;

    private string $code;

    private string $name;

    private DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, Permission>
     */
    private Collection $permissions;

    public function __construct(
        string $code,
        string $name,
        ?Tenant $tenant = null,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->code = $code;
        $this->name = $name;
        $this->tenant = $tenant;
        $this->createdAt = new DateTimeImmutable();
        $this->permissions = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function isGlobal(): bool
    {
        return null === $this->tenant;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, Permission>
     */
    public function getPermissions(): Collection
    {
        return $this->permissions;
    }

    public function grantPermission(Permission $permission): void
    {
        if (!$this->permissions->contains($permission)) {
            $this->permissions->add($permission);
        }
    }

    public function revokePermission(Permission $permission): void
    {
        $this->permissions->removeElement($permission);
    }
}
