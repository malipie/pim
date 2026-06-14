<?php

declare(strict_types=1);

namespace App\Import\Domain\Entity;

use App\Shared\Application\TenantScoped;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use LogicException;
use Symfony\Component\Uid\Uuid;

/**
 * IMP2-2.2 — a source file uploaded ONCE at wizard Step 1 (parse-preview)
 * and reused by the dry-run + start steps via its {@see $id}, so the same
 * bytes are not re-sent (and re-parsed) three times.
 *
 * The bytes live on the `imports.storage` Flysystem disk under
 * `{tenant}/staged/{uuid}/{filename}`; this row is the addressable handle
 * plus the ownership + TTL metadata. A {@see \App\Import\Presentation\Command\PurgeStagedFilesCommand}
 * deletes rows + objects older than 24h.
 *
 * Tenant-scoped (RLS + Doctrine filter) and additionally owner-scoped: a
 * staged_file_id only resolves for the user that uploaded it.
 */
class StagedFile implements TenantScoped
{
    private Uuid $id;

    private ?Tenant $tenant = null;

    /** Bare uuid to avoid cross-BC coupling with Identity\Domain\Entity\User. */
    private Uuid $userId;

    private string $fileName;

    private int $sizeBytes;

    private string $storageKey;

    private DateTimeImmutable $createdAt;

    public function __construct(
        Uuid $userId,
        string $fileName,
        int $sizeBytes,
        string $storageKey,
        ?Uuid $id = null,
        ?DateTimeImmutable $createdAt = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->userId = $userId;
        $this->fileName = $fileName;
        $this->sizeBytes = $sizeBytes;
        $this->storageKey = $storageKey;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
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

    public function getUserId(): Uuid
    {
        return $this->userId;
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function getSizeBytes(): int
    {
        return $this->sizeBytes;
    }

    public function getStorageKey(): string
    {
        return $this->storageKey;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
