<?php

declare(strict_types=1);

namespace App\Import\Domain\Entity;

use App\Import\Domain\Enum\ImportSourceHealth;
use App\Import\Domain\Enum\ImportSourceType;
use App\Shared\Application\TenantScoped;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use LogicException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * VIEW-IMP-03 (#500) — connection config for incoming file feeds.
 *
 * Lives alongside the profiles + sessions tables in the Import bundle.
 * Tenant-scoped + owner-scoped; the operator only sees the sources
 * they registered. `authRef` is a pointer to the Symfony Secrets Vault
 * — credential plaintext never lands in the DB.
 *
 * Polling daemon is not part of V03 — only the schema, CRUD endpoints,
 * and a manual `test-connection` probe land here. Auto-pickup of files
 * matching `filePattern` is scoped to the polling follow-up.
 */
class ImportSource extends AggregateRoot implements TenantScoped
{
    private Uuid $id;

    private ?Tenant $tenant = null;

    private Uuid $userId;

    private ?ImportProfile $profile = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $name;

    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    #[Assert\Regex(pattern: '/^[a-z0-9-]+$/', message: 'Code must contain only lowercase letters, digits, and dashes.')]
    private string $code;

    private string $type;

    private ?string $host = null;

    private ?string $path = null;

    private ?string $filePattern = null;

    private ?string $authRef = null;

    private ?int $pollIntervalSec = null;

    private bool $autotrigger = false;

    private string $health = ImportSourceHealth::Off->value;

    private ?DateTimeImmutable $healthCheckedAt = null;

    private ?string $healthNote = null;

    private ?DateTimeImmutable $lastPickupAt = null;

    private int $files24h = 0;

    private DateTimeImmutable $createdAt;

    private DateTimeImmutable $updatedAt;

    public function __construct(
        Uuid $userId,
        string $name,
        string $code,
        ImportSourceType $type,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->userId = $userId;
        $this->name = $name;
        $this->code = $code;
        $this->type = $type->value;
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

    public function getProfile(): ?ImportProfile
    {
        return $this->profile;
    }

    public function setProfile(?ImportProfile $profile): void
    {
        $this->profile = $profile;
        $this->touch();
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

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
        $this->touch();
    }

    public function getType(): ImportSourceType
    {
        return ImportSourceType::from($this->type);
    }

    public function setType(ImportSourceType $type): void
    {
        $this->type = $type->value;
        $this->touch();
    }

    public function getHost(): ?string
    {
        return $this->host;
    }

    public function setHost(?string $host): void
    {
        $this->host = $host;
        $this->touch();
    }

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(?string $path): void
    {
        $this->path = $path;
        $this->touch();
    }

    public function getFilePattern(): ?string
    {
        return $this->filePattern;
    }

    public function setFilePattern(?string $pattern): void
    {
        $this->filePattern = $pattern;
        $this->touch();
    }

    public function getAuthRef(): ?string
    {
        return $this->authRef;
    }

    public function setAuthRef(?string $ref): void
    {
        $this->authRef = $ref;
        $this->touch();
    }

    public function getPollIntervalSec(): ?int
    {
        return $this->pollIntervalSec;
    }

    public function setPollIntervalSec(?int $seconds): void
    {
        $this->pollIntervalSec = $seconds;
        $this->touch();
    }

    public function isAutotrigger(): bool
    {
        return $this->autotrigger;
    }

    public function setAutotrigger(bool $value): void
    {
        $this->autotrigger = $value;
        $this->touch();
    }

    public function getHealth(): ImportSourceHealth
    {
        return ImportSourceHealth::from($this->health);
    }

    public function recordHealth(ImportSourceHealth $health, ?string $note, DateTimeImmutable $at): void
    {
        $this->health = $health->value;
        $this->healthNote = $note;
        $this->healthCheckedAt = $at;
        $this->touch();
    }

    public function getHealthCheckedAt(): ?DateTimeImmutable
    {
        return $this->healthCheckedAt;
    }

    public function getHealthNote(): ?string
    {
        return $this->healthNote;
    }

    public function getLastPickupAt(): ?DateTimeImmutable
    {
        return $this->lastPickupAt;
    }

    public function getFiles24h(): int
    {
        return $this->files24h;
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
