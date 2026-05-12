<?php

declare(strict_types=1);

namespace App\Import\Domain\Entity;

use App\Import\Domain\Enum\ScheduleRunStatus;
use App\Shared\Application\TenantScoped;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use LogicException;
use Symfony\Component\Uid\Uuid;

class ImportScheduleRun extends AggregateRoot implements TenantScoped
{
    private Uuid $id;

    private Uuid $scheduleId;

    private ?Tenant $tenant = null;

    private DateTimeImmutable $triggeredAt;

    private string $status;

    private ?int $durationMs = null;

    private ?string $errorMessage = null;

    private ?Uuid $sessionId = null;

    public function __construct(
        Uuid $scheduleId,
        ScheduleRunStatus $status,
        ?int $durationMs = null,
        ?Uuid $sessionId = null,
        ?string $errorMessage = null,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->scheduleId = $scheduleId;
        $this->status = $status->value;
        $this->durationMs = $durationMs;
        $this->sessionId = $sessionId;
        $this->errorMessage = $errorMessage;
        $this->triggeredAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getScheduleId(): Uuid
    {
        return $this->scheduleId;
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

    public function getStatus(): ScheduleRunStatus
    {
        return ScheduleRunStatus::from($this->status);
    }

    public function getDurationMs(): ?int
    {
        return $this->durationMs;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getSessionId(): ?Uuid
    {
        return $this->sessionId;
    }

    public function getTriggeredAt(): DateTimeImmutable
    {
        return $this->triggeredAt;
    }
}
