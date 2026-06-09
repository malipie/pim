<?php

declare(strict_types=1);

namespace App\Export\Domain\Entity;

use App\Export\Domain\Enum\ExportEntityType;
use App\Shared\Application\TenantScoped;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use LogicException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Per-user saved configuration for the export wizard (PRD §3.3, §5.1).
 *
 * Magda saves "SEO round-trip PL+EN" once, hits "Run now" weekly without
 * reconfiguring columns/locales/format. Per-user only in MVP (share with
 * team = Faza 1). UNIQUE(tenant_id, user_id, name) enforced at DB level.
 *
 * `config` JSONB shape (PRD §5.3):
 *   { format, encoding?, selected_columns, locales, channels,
 *     include_variants, default_target_scope, _meta? }
 *
 * Editing a profile only affects future runs — historical
 * {@see ExportSession} rows preserve their own `selected_columns`/config.
 */
class ExportProfile extends AggregateRoot implements TenantScoped
{
    private Uuid $id;

    private ?Tenant $tenant = null;

    private Uuid $userId;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $name;

    private ?string $description = null;

    private string $entityType;

    /** Bare uuid → Catalog\ObjectType; the FK lives at the DB level only (cross-BC decoupling). */
    private ?Uuid $objectTypeId = null;

    /** @var array<string, mixed> */
    private array $config;

    private ?DateTimeImmutable $lastRunAt = null;

    private int $runCount = 0;

    private DateTimeImmutable $createdAt;

    private DateTimeImmutable $updatedAt;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        Uuid $userId,
        string $name,
        array $config,
        ?string $description = null,
        ExportEntityType $entityType = ExportEntityType::Product,
        ?Uuid $objectTypeId = null,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->userId = $userId;
        $this->name = $name;
        $this->config = $config;
        $this->description = $description;
        $this->entityType = $entityType->value;
        $this->objectTypeId = $objectTypeId;
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function assignTenant(Tenant $tenant): void
    {
        if (null !== $this->tenant) {
            throw new LogicException('Tenant is already assigned and cannot be reassigned.');
        }
        $this->tenant = $tenant;
    }

    public function getUserId(): Uuid
    {
        return $this->userId;
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

    public function setDescription(?string $description): void
    {
        $this->description = $description;
        $this->touch();
    }

    public function getEntityType(): ExportEntityType
    {
        return ExportEntityType::from($this->entityType);
    }

    public function getObjectTypeId(): ?Uuid
    {
        return $this->objectTypeId;
    }

    public function reclassify(ExportEntityType $entityType, ?Uuid $objectTypeId): void
    {
        $this->entityType = $entityType->value;
        $this->objectTypeId = $objectTypeId;
        $this->touch();
    }

    /** @return array<string, mixed> */
    public function getConfig(): array
    {
        return $this->config;
    }

    /** @param array<string, mixed> $config */
    public function updateConfig(array $config): void
    {
        $this->config = $config;
        $this->touch();
    }

    public function getLastRunAt(): ?DateTimeImmutable
    {
        return $this->lastRunAt;
    }

    public function getRunCount(): int
    {
        return $this->runCount;
    }

    public function recordRun(): void
    {
        ++$this->runCount;
        $this->lastRunAt = new DateTimeImmutable();
        $this->touch();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isOwnedBy(Uuid $userId): bool
    {
        return $this->userId->equals($userId);
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
