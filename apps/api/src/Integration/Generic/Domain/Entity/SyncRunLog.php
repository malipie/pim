<?php

declare(strict_types=1);

namespace App\Integration\Generic\Domain\Entity;

use App\Integration\Generic\Domain\Enum\SyncRecordAction;
use App\Shared\Application\TenantScoped;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use LogicException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Per-record detail of a {@see SyncRun} (ADR-0022, epic APIC, ticket APIC-P3-02):
 * the match key that identified the row, the action taken, the fields touched
 * and an optional message (the error text for a failed record).
 *
 * `TenantScoped` + Postgres RLS isolate every log line to its tenant; the FK to
 * SyncRun cascades on delete.
 */
class SyncRunLog extends AggregateRoot implements TenantScoped
{
    private Uuid $id;

    private ?Tenant $tenant = null;

    private SyncRun $run;

    #[Assert\Length(max: 255)]
    private ?string $matchKey = null;

    private string $action;

    /**
     * The fields written/compared for this record; null for skips/failures.
     *
     * @var array<string, mixed>|null
     */
    private ?array $fields = null;

    private ?string $message = null;

    private DateTimeImmutable $createdAt;

    public function __construct(
        SyncRun $run,
        SyncRecordAction $action,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->run = $run;
        $this->action = $action->value;
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
            throw new LogicException('Tenant is already assigned and cannot be reassigned.');
        }

        $this->tenant = $tenant;
    }

    public function getRun(): SyncRun
    {
        return $this->run;
    }

    public function getRunId(): Uuid
    {
        return $this->run->getId();
    }

    public function getMatchKey(): ?string
    {
        return $this->matchKey;
    }

    public function setMatchKey(?string $matchKey): void
    {
        $this->matchKey = $matchKey;
    }

    public function getAction(): SyncRecordAction
    {
        return SyncRecordAction::from($this->action);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getFields(): ?array
    {
        return $this->fields;
    }

    /**
     * @param array<string, mixed>|null $fields
     */
    public function setFields(?array $fields): void
    {
        $this->fields = $fields;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): void
    {
        $this->message = $message;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
