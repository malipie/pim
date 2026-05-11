<?php

declare(strict_types=1);

namespace App\Import\Domain\Entity;

use App\Shared\Application\TenantScoped;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use LogicException;
use Symfony\Component\Uid\Uuid;

/**
 * VIEW-IMP-03 (#500) — audit trail entry for an {@see ImportSource}.
 *
 * One row per health-check or pickup attempt. Severity is rendered as
 * a colored bar in the source logs drawer.
 */
class ImportSourceLog extends AggregateRoot implements TenantScoped
{
    public const string EVENT_HEALTH_CHECK = 'health_check';
    public const string EVENT_FILE_PICKED_UP = 'file_picked_up';
    public const string EVENT_PICKUP_FAILED = 'pickup_failed';

    public const string SEVERITY_INFO = 'info';
    public const string SEVERITY_WARN = 'warn';
    public const string SEVERITY_ERROR = 'error';

    private Uuid $id;

    private Uuid $sourceId;

    private ?Tenant $tenant = null;

    private string $eventType;

    private string $severity;

    /** @var array<string, mixed> */
    private array $payload;

    private DateTimeImmutable $createdAt;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        Uuid $sourceId,
        string $eventType,
        string $severity,
        array $payload = [],
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->sourceId = $sourceId;
        $this->eventType = $eventType;
        $this->severity = $severity;
        $this->payload = $payload;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSourceId(): Uuid
    {
        return $this->sourceId;
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

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
