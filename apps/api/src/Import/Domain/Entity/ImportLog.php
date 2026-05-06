<?php

declare(strict_types=1);

namespace App\Import\Domain\Entity;

use App\Import\Domain\Enum\ImportLogLevel;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Per-row log entry feeding the validation preview, the live progress
 * stream, and the post-import CSV report.
 *
 * Not tenant-scoped on its own — tenant isolation flows through the
 * parent {@see ImportSession}, and the FK has `ON DELETE CASCADE` so
 * dropping a session deletes its log trail.
 */
class ImportLog
{
    private Uuid $id;

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
