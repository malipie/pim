<?php

declare(strict_types=1);

namespace App\Export\Domain\Entity;

use App\Export\Domain\Enum\ExportLogLevel;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Per-job log entry (PRD §5.1) feeding the session detail view and the
 * post-run report.
 *
 * Not tenant-scoped on its own — tenant isolation flows through the
 * parent {@see ExportSession}, and the FK has `ON DELETE CASCADE` so
 * deleting a session deletes its log trail.
 */
class ExportLog
{
    private Uuid $id;

    private ExportSession $exportSession;

    private string $level;

    private string $message;

    /** @var array<string, mixed>|null */
    private ?array $context = null;

    private DateTimeImmutable $createdAt;

    /**
     * @param array<string, mixed>|null $context
     */
    public function __construct(
        ExportSession $exportSession,
        ExportLogLevel $level,
        string $message,
        ?array $context = null,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->exportSession = $exportSession;
        $this->level = $level->value;
        $this->message = $message;
        $this->context = $context;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getExportSession(): ExportSession
    {
        return $this->exportSession;
    }

    public function getLevel(): ExportLogLevel
    {
        return ExportLogLevel::from($this->level);
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    /** @return array<string, mixed>|null */
    public function getContext(): ?array
    {
        return $this->context;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
