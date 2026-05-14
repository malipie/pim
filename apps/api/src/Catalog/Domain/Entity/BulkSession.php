<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use App\Shared\Application\TenantScoped;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use LogicException;
use Symfony\Component\Uid\Uuid;

/**
 * VIEW-12 (#543) — bulk operation session.
 *
 * One row per Apply click in the bulk wizard (or Cmd+K dispatch in
 * VIEW-19). Tracks counts (success/skipped/error) + 24h rollback
 * window + source attribution.
 *
 * @phpstan-type ActionPayload array<string, mixed>
 */
class BulkSession implements TenantScoped
{
    public const string SOURCE_MANUAL = 'manual';
    public const string SOURCE_CMD_K_AGENT = 'cmd_k_agent';

    private Uuid $id;
    private ?Tenant $tenant = null;
    private ?Uuid $userId;
    private string $actionType;

    /** @var list<string> array of UUID strings */
    private array $targetObjectIds;

    private int $targetCount;
    private int $successCount = 0;
    private int $skippedCount = 0;
    private int $errorCount = 0;

    /** @var array<string, mixed> */
    private array $actionPayload;

    private ?DateTimeImmutable $rollbackAvailableUntil = null;
    private ?DateTimeImmutable $rolledBackAt = null;
    private string $source;
    private ?string $cmdKCommand = null;
    private DateTimeImmutable $startedAt;
    private ?DateTimeImmutable $completedAt = null;

    /**
     * @param list<string>         $targetObjectIds
     * @param array<string, mixed> $actionPayload
     */
    public function __construct(
        string $actionType,
        array $targetObjectIds,
        array $actionPayload,
        ?Uuid $userId = null,
        string $source = self::SOURCE_MANUAL,
        ?string $cmdKCommand = null,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->actionType = $actionType;
        $this->targetObjectIds = $targetObjectIds;
        $this->targetCount = \count($this->targetObjectIds);
        $this->actionPayload = $actionPayload;
        $this->userId = $userId;
        $this->source = $source;
        $this->cmdKCommand = $cmdKCommand;
        $this->startedAt = new DateTimeImmutable();
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
            throw new LogicException('Tenant already assigned.');
        }
        $this->tenant = $tenant;
    }

    public function getUserId(): ?Uuid
    {
        return $this->userId;
    }

    public function getActionType(): string
    {
        return $this->actionType;
    }

    /**
     * @return list<string>
     */
    public function getTargetObjectIds(): array
    {
        return $this->targetObjectIds;
    }

    public function getTargetCount(): int
    {
        return $this->targetCount;
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }

    public function getErrorCount(): int
    {
        return $this->errorCount;
    }

    /**
     * @return array<string, mixed>
     */
    public function getActionPayload(): array
    {
        return $this->actionPayload;
    }

    public function getRollbackAvailableUntil(): ?DateTimeImmutable
    {
        return $this->rollbackAvailableUntil;
    }

    public function getRolledBackAt(): ?DateTimeImmutable
    {
        return $this->rolledBackAt;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function getCmdKCommand(): ?string
    {
        return $this->cmdKCommand;
    }

    public function getStartedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function complete(int $successCount, int $skippedCount, int $errorCount): void
    {
        $this->successCount = $successCount;
        $this->skippedCount = $skippedCount;
        $this->errorCount = $errorCount;
        $this->completedAt = new DateTimeImmutable();
        // 24h rollback window per PRD §13.1.
        $this->rollbackAvailableUntil = $this->completedAt->modify('+24 hours');
    }

    public function markRolledBack(): void
    {
        if (null === $this->rolledBackAt) {
            $this->rolledBackAt = new DateTimeImmutable();
        }
    }

    public function isRollbackAvailable(): bool
    {
        if (null !== $this->rolledBackAt) {
            return false;
        }
        if (null === $this->rollbackAvailableUntil) {
            return false;
        }

        return new DateTimeImmutable() < $this->rollbackAvailableUntil;
    }
}
