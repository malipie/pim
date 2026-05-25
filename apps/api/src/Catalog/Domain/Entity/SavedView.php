<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use App\Shared\Application\TenantScoped;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use LogicException;
use Symfony\Component\Uid\Uuid;

/**
 * UI-02.7 (#297) — per-user list state persistence.
 *
 * Owned by `(tenant_id, user_id)`; `user_id IS NULL` is the system
 * tenant-wide template lane (e.g. seeded "Default") which the UI
 * surfaces for everyone but keeps non-deletable.
 */
class SavedView implements TenantScoped
{
    private Uuid $id;
    private ?Tenant $tenant = null;
    private ?Uuid $userId;
    private string $slug;
    private string $name;
    private ?string $description = null;
    private string $resource;
    /**
     * ULV-01 (#982) — scopes a saved view to a specific ObjectType. Nullable
     * for backward compatibility with the legacy `resource` string column;
     * ULV-06 / ULV-11 finish the cutover and start enforcing non-null.
     */
    private ?ObjectType $objectType = null;
    /**
     * @var array<string, mixed>
     */
    private array $config;
    private bool $isDefault = false;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        string $slug,
        string $name,
        string $resource,
        array $config,
        ?Uuid $userId = null,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->slug = $slug;
        $this->name = $name;
        $this->resource = $resource;
        $this->config = $config;
        $this->userId = $userId;
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

    public function getUserId(): ?Uuid
    {
        return $this->userId;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        $this->name = $name;
        $this->touch();
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function changeDescription(?string $description): void
    {
        $this->description = $description;
        $this->touch();
    }

    public function getResource(): string
    {
        return $this->resource;
    }

    public function getObjectType(): ?ObjectType
    {
        return $this->objectType;
    }

    public function assignObjectType(?ObjectType $objectType): void
    {
        $this->objectType = $objectType;
        $this->touch();
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function updateConfig(array $config): void
    {
        $this->config = $config;
        $this->touch();
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function markDefault(bool $isDefault): void
    {
        $this->isDefault = $isDefault;
        $this->touch();
    }

    public function isSystem(): bool
    {
        return null === $this->userId;
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
