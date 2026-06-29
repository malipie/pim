<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Domain\Entity;

use App\ApiConfigurator\Domain\Enum\WebhookDeliveryStatus;
use App\Shared\Application\TenantScoped;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use LogicException;
use Symfony\Component\Uid\Uuid;

/**
 * Audit record of one outbound webhook delivery (APIC-P4-05).
 *
 * Created `pending` by the {@see \App\ApiConfigurator\Application\Subscriber\WebhookDeliverySubscriber}
 * when a domain event fans out to a profile, then driven to `delivered`/`failed`
 * by {@see \App\ApiConfigurator\Application\Handler\WebhookDeliveryHandler} as
 * Messenger retries the {@see \App\ApiConfigurator\Domain\Message\WebhookDeliveryMessage}.
 * The signed `payload` is stored so each retry re-sends the exact body.
 * `TenantScoped` + Postgres RLS isolate every record to its tenant.
 */
class WebhookDelivery implements TenantScoped
{
    private Uuid $id;

    private ?Tenant $tenant = null;

    private Uuid $profileId;

    private string $eventType;

    private string $targetUrl;

    /** @var array<string, mixed> */
    private array $payload;

    private string $status = WebhookDeliveryStatus::Pending->value;

    private int $attempts = 0;

    private ?int $httpStatus = null;

    private ?int $durationMs = null;

    private ?string $lastError = null;

    private DateTimeImmutable $createdAt;

    private DateTimeImmutable $updatedAt;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        Uuid $profileId,
        string $eventType,
        string $targetUrl,
        array $payload,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->profileId = $profileId;
        $this->eventType = $eventType;
        $this->targetUrl = $targetUrl;
        $this->payload = $payload;
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
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

    public function getProfileId(): Uuid
    {
        return $this->profileId;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function getTargetUrl(): string
    {
        return $this->targetUrl;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getStatus(): WebhookDeliveryStatus
    {
        return WebhookDeliveryStatus::from($this->status);
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function getDurationMs(): ?int
    {
        return $this->durationMs;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function markDelivered(int $httpStatus, int $durationMs): void
    {
        ++$this->attempts;
        $this->status = WebhookDeliveryStatus::Delivered->value;
        $this->httpStatus = $httpStatus;
        $this->durationMs = $durationMs;
        $this->lastError = null;
        $this->touch();
    }

    public function markFailed(?int $httpStatus, int $durationMs, string $error): void
    {
        ++$this->attempts;
        $this->status = WebhookDeliveryStatus::Failed->value;
        $this->httpStatus = $httpStatus;
        $this->durationMs = $durationMs;
        $this->lastError = mb_substr($error, 0, 1000);
        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
