<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use App\Shared\Application\TenantScoped;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use LogicException;
use Symfony\Component\Uid\Uuid;

/**
 * UI-02.3 (#293) — bulk-edit job audit + status record.
 *
 * One row per POST `/api/products/bulk-edit`. MVP executes the
 * operation synchronously inside the request and updates this row to
 * `completed` / `failed` before responding; the GET status endpoint
 * exists so the frontend can recover after a tab close (Faza 1 async
 * dispatch will re-use the same row).
 */
class BulkEditJob implements TenantScoped
{
    public const string STATUS_PENDING = 'pending';
    public const string STATUS_RUNNING = 'running';
    public const string STATUS_COMPLETED = 'completed';
    public const string STATUS_FAILED = 'failed';

    private Uuid $id;
    private ?Tenant $tenant = null;
    private ?Uuid $userId;
    private string $operation;
    /**
     * @var array<string, mixed>
     */
    private array $payload;
    private int $total = 0;
    private int $processed = 0;
    private int $errorsCount = 0;
    /**
     * @var list<array{objectId: string, message: string}>
     */
    private array $firstErrors = [];
    private string $status = self::STATUS_PENDING;
    private DateTimeImmutable $createdAt;
    private ?DateTimeImmutable $completedAt = null;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        string $operation,
        array $payload,
        int $total,
        ?Uuid $userId = null,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->operation = $operation;
        $this->payload = $payload;
        $this->total = $total;
        $this->userId = $userId;
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

    public function getUserId(): ?Uuid
    {
        return $this->userId;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getProcessed(): int
    {
        return $this->processed;
    }

    public function recordProgress(int $processed): void
    {
        $this->processed = $processed;
    }

    public function getErrorsCount(): int
    {
        return $this->errorsCount;
    }

    /**
     * @return list<array{objectId: string, message: string}>
     */
    public function getFirstErrors(): array
    {
        return $this->firstErrors;
    }

    public function recordError(string $objectId, string $message): void
    {
        ++$this->errorsCount;
        if (\count($this->firstErrors) < 100) {
            $this->firstErrors[] = ['objectId' => $objectId, 'message' => $message];
        }
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function markRunning(): void
    {
        $this->status = self::STATUS_RUNNING;
    }

    public function markCompleted(): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completedAt = new DateTimeImmutable();
    }

    public function markFailed(): void
    {
        $this->status = self::STATUS_FAILED;
        $this->completedAt = new DateTimeImmutable();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCompletedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }
}
