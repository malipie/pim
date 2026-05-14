<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * VIEW-12 (#543) — per-(session, object, attribute) change log.
 *
 * Append-only. The 24h rollback executor (VIEW-17) iterates these in
 * reverse to restore `oldValue`. `attributeId` may be NULL for
 * destructive ops (delete) where the whole row was removed.
 *
 * Retention: hard delete after 7 days (24h rollback window + buffer).
 */
class BulkLog
{
    public const string LEVEL_INFO = 'info';
    public const string LEVEL_WARNING = 'warning';
    public const string LEVEL_ERROR = 'error';

    private Uuid $id;
    private Uuid $bulkSessionId;
    private Uuid $objectId;
    private ?Uuid $attributeId;
    private mixed $oldValue;
    private mixed $newValue;
    private string $level;
    private ?string $message;
    private DateTimeImmutable $createdAt;

    public function __construct(
        Uuid $bulkSessionId,
        Uuid $objectId,
        ?Uuid $attributeId = null,
        mixed $oldValue = null,
        mixed $newValue = null,
        string $level = self::LEVEL_INFO,
        ?string $message = null,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->bulkSessionId = $bulkSessionId;
        $this->objectId = $objectId;
        $this->attributeId = $attributeId;
        $this->oldValue = $oldValue;
        $this->newValue = $newValue;
        $this->level = $level;
        $this->message = $message;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getBulkSessionId(): Uuid
    {
        return $this->bulkSessionId;
    }

    public function getObjectId(): Uuid
    {
        return $this->objectId;
    }

    public function getAttributeId(): ?Uuid
    {
        return $this->attributeId;
    }

    public function getOldValue(): mixed
    {
        return $this->oldValue;
    }

    public function getNewValue(): mixed
    {
        return $this->newValue;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
