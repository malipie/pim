<?php

declare(strict_types=1);

namespace App\Import\Domain\Entity;

use App\Import\Domain\Enum\ImportLogLevel;
use App\Shared\Application\TenantScoped;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use LogicException;
use Symfony\Component\Uid\Uuid;

/**
 * Per-row log entry feeding the validation preview, the live progress
 * stream, and the post-import CSV report.
 *
 * IMP2-2.5 (#1481) — carries its own `tenant_id` so Postgres RLS can isolate
 * the trail directly (FORCE RLS readiness), not only through the parent
 * {@see ImportSession} FK. The tenant is stamped on `prePersist` by
 * {@see \App\Shared\Infrastructure\Doctrine\EventListener\TenantAssignmentListener}
 * from the active context — every log is created inside an import run that
 * already has the tenant resolved (HTTP request or worker rebinding). The
 * session FK keeps `ON DELETE CASCADE` so dropping a session still drops its
 * log trail.
 */
class ImportLog implements TenantScoped
{
    private Uuid $id;

    private ?Tenant $tenant = null;

    private ImportSession $importSession;

    private int $rowNumber;

    private ?string $sku = null;

    private string $level;

    private ?string $errorType = null;

    private string $message;

    private ?string $columnName = null;

    private ?string $columnValue = null;

    private DateTimeImmutable $createdAt;

    public function __construct(
        ImportSession $importSession,
        int $rowNumber,
        ImportLogLevel $level,
        string $message,
        ?string $sku = null,
        ?string $errorType = null,
        ?string $columnName = null,
        ?string $columnValue = null,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->importSession = $importSession;
        $this->rowNumber = $rowNumber;
        $this->level = $level->value;
        $this->message = $message;
        $this->sku = $sku;
        $this->errorType = $errorType;
        $this->columnName = $columnName;
        $this->columnValue = $columnValue;
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

    public function assignTenant(Tenant $tenant): void
    {
        if (null !== $this->tenant) {
            throw new LogicException('Import log tenant is already assigned.');
        }
        $this->tenant = $tenant;
    }

    public function getImportSession(): ImportSession
    {
        return $this->importSession;
    }

    public function getRowNumber(): int
    {
        return $this->rowNumber;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function getLevel(): ImportLogLevel
    {
        return ImportLogLevel::from($this->level);
    }

    public function getErrorType(): ?string
    {
        return $this->errorType;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getColumnName(): ?string
    {
        return $this->columnName;
    }

    public function getColumnValue(): ?string
    {
        return $this->columnValue;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
