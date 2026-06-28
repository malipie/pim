<?php

declare(strict_types=1);

namespace App\Integration\Generic\Domain\Entity;

use App\Integration\Generic\Domain\Enum\SyncDirection;
use App\Integration\Generic\Domain\Enum\SyncRunStatus;
use App\Shared\Application\TenantScoped;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use LogicException;
use Symfony\Component\Uid\Uuid;

/**
 * The audit record of one sync execution of a {@see SyncBinding} (ADR-0022,
 * epic APIC, ticket APIC-P3-02; mirrors `ImportScheduleRun` + `sync_job_logs`).
 *
 * Captures the direction, lifecycle status, per-outcome counters and the cursor
 * before/after the run (so a delta sync is auditable and resumable). Per-record
 * detail lives in {@see SyncRunLog}. `TenantScoped` + Postgres RLS isolate every
 * run to its tenant.
 */
class SyncRun extends AggregateRoot implements TenantScoped
{
    private Uuid $id;

    private ?Tenant $tenant = null;

    private SyncBinding $binding;

    private string $direction;

    private DateTimeImmutable $startedAt;

    private ?DateTimeImmutable $finishedAt = null;

    private string $status = SyncRunStatus::Running->value;

    private int $createdCount = 0;

    private int $updatedCount = 0;

    private int $skippedCount = 0;

    private int $failedCount = 0;

    /** @var array<string, mixed>|null */
    private ?array $cursorBefore = null;

    /** @var array<string, mixed>|null */
    private ?array $cursorAfter = null;

    public function __construct(
        SyncBinding $binding,
        SyncDirection $direction,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->binding = $binding;
        $this->direction = $direction->value;
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
            throw new LogicException('Tenant is already assigned and cannot be reassigned.');
        }

        $this->tenant = $tenant;
    }

    public function getBinding(): SyncBinding
    {
        return $this->binding;
    }

    public function getBindingId(): Uuid
    {
        return $this->binding->getId();
    }

    public function getDirection(): SyncDirection
    {
        return SyncDirection::from($this->direction);
    }

    public function getStartedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): ?DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function getStatus(): SyncRunStatus
    {
        return SyncRunStatus::from($this->status);
    }

    public function getCreatedCount(): int
    {
        return $this->createdCount;
    }

    public function getUpdatedCount(): int
    {
        return $this->updatedCount;
    }

    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }

    public function getFailedCount(): int
    {
        return $this->failedCount;
    }

    public function recordCreated(): void
    {
        ++$this->createdCount;
    }

    public function recordUpdated(): void
    {
        ++$this->updatedCount;
    }

    public function recordSkipped(): void
    {
        ++$this->skippedCount;
    }

    public function recordFailed(): void
    {
        ++$this->failedCount;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCursorBefore(): ?array
    {
        return $this->cursorBefore;
    }

    /**
     * @param array<string, mixed>|null $cursorBefore
     */
    public function setCursorBefore(?array $cursorBefore): void
    {
        $this->cursorBefore = $cursorBefore;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getCursorAfter(): ?array
    {
        return $this->cursorAfter;
    }

    /**
     * Stamps the terminal status, finish time and final cursor. The status is
     * derived from the failed counter unless overridden.
     *
     * @param array<string, mixed>|null $cursorAfter
     */
    public function markFinished(?SyncRunStatus $status = null, ?array $cursorAfter = null): void
    {
        $this->finishedAt = new DateTimeImmutable();
        $this->cursorAfter = $cursorAfter;
        $this->status = ($status ?? $this->deriveStatus())->value;
    }

    private function deriveStatus(): SyncRunStatus
    {
        if (0 === $this->failedCount) {
            return SyncRunStatus::Success;
        }

        return ($this->createdCount + $this->updatedCount) > 0
            ? SyncRunStatus::Partial
            : SyncRunStatus::Failed;
    }
}
