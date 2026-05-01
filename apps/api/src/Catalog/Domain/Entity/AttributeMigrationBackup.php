<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * UI-08.6 (#261) — append-only snapshot of `object_values` rows captured
 * before an Attribute type migration.
 *
 * The application writes these rows via DBAL inside
 * `AttributeMigrationExecutor` for performance — bulk migrations may
 * touch thousands of rows and we don't want to hydrate them into the
 * Unit of Work. The entity exists primarily so:
 *
 *   - the schema is reflected by Doctrine schema-tool (Zenstruck Foundry
 *     ResetDatabase uses schema-tool, not migrations, when re-creating
 *     test databases — without this entity the backup table would not
 *     exist in tests);
 *   - a future "Restore from backup" endpoint (#UI-08.12 follow-up) has
 *     a typed read path through `EntityManager::find()`.
 *
 * No setters / mutators — the row is created in one shot by
 * `AttributeMigrationExecutor::snapshot()` via DBAL and never changes
 * afterwards.
 */
class AttributeMigrationBackup
{
    private Uuid $id;
    private Uuid $attributeId;
    private string $sourceType;
    private string $targetType;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $snapshot;

    private int $rowCount;
    private DateTimeImmutable $createdAt;

    /**
     * @param array<int, array<string, mixed>> $snapshot
     */
    public function __construct(
        Uuid $attributeId,
        string $sourceType,
        string $targetType,
        array $snapshot,
        int $rowCount,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->attributeId = $attributeId;
        $this->sourceType = $sourceType;
        $this->targetType = $targetType;
        $this->snapshot = $snapshot;
        $this->rowCount = $rowCount;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getAttributeId(): Uuid
    {
        return $this->attributeId;
    }

    public function getSourceType(): string
    {
        return $this->sourceType;
    }

    public function getTargetType(): string
    {
        return $this->targetType;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getSnapshot(): array
    {
        return $this->snapshot;
    }

    public function getRowCount(): int
    {
        return $this->rowCount;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
