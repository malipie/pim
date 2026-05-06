<?php

declare(strict_types=1);

namespace App\Backup\Domain\Entity;

use App\Backup\Domain\Enum\BackupStatus;
use App\Backup\Domain\Enum\BackupTriggerAction;
use App\Shared\Application\TenantScoped;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use LogicException;
use Symfony\Component\Uid\Uuid;

/**
 * One pgBackRest snapshot triggered manually (Step 4 wizard checkbox)
 * or scheduled. The state machine wraps the long-running CLI invocation
 * launched by the IMP-06 handler.
 */
class Backup extends AggregateRoot implements TenantScoped
{
    private Uuid $id;

    private ?Tenant $tenant = null;

    private Uuid $triggeredByUserId;

    private string $triggeredByAction;

    private ?string $pgbackrestLabel = null;

    private string $status = BackupStatus::Pending->value;

    private ?int $sizeBytes = null;

    private DateTimeImmutable $startedAt;

    private ?DateTimeImmutable $completedAt = null;

    private ?string $errorMessage = null;

    public function __construct(
        Uuid $triggeredByUserId,
        BackupTriggerAction $triggeredByAction,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->triggeredByUserId = $triggeredByUserId;
        $this->triggeredByAction = $triggeredByAction->value;
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

    public function getTriggeredByUserId(): Uuid
    {
        return $this->triggeredByUserId;
    }

    public function getTriggeredByAction(): BackupTriggerAction
    {
        return BackupTriggerAction::from($this->triggeredByAction);
    }

    public function getPgbackrestLabel(): ?string
    {
        return $this->pgbackrestLabel;
    }

    public function setPgbackrestLabel(?string $label): void
    {
        $this->pgbackrestLabel = $label;
    }

    public function getStatus(): BackupStatus
    {
        return BackupStatus::from($this->status);
    }

    public function getSizeBytes(): ?int
    {
        return $this->sizeBytes;
    }

    public function getStartedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function markRunning(): void
    {
        $this->ensureTransitionable([BackupStatus::Pending]);
        $this->status = BackupStatus::Running->value;
    }

    public function markCompleted(int $sizeBytes, ?string $pgbackrestLabel = null): void
    {
        $this->ensureTransitionable([BackupStatus::Running]);
        $this->status = BackupStatus::Completed->value;
        $this->sizeBytes = $sizeBytes;
        $this->pgbackrestLabel = $pgbackrestLabel ?? $this->pgbackrestLabel;
        $this->completedAt = new DateTimeImmutable();
    }

    public function markFailed(string $message): void
    {
        $this->ensureTransitionable([BackupStatus::Pending, BackupStatus::Running]);
        $this->status = BackupStatus::Failed->value;
        $this->errorMessage = $message;
        $this->completedAt = new DateTimeImmutable();
    }

    /**
     * @param list<BackupStatus> $allowed
     */
    private function ensureTransitionable(array $allowed): void
    {
        $current = $this->getStatus();
        foreach ($allowed as $status) {
            if ($status === $current) {
                return;
            }
        }
        throw new LogicException(\sprintf(
            'Cannot transition backup %s from "%s".',
            $this->id->toRfc4122(),
            $this->status,
        ));
    }
}
