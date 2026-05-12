<?php

declare(strict_types=1);

namespace App\Import\Domain\Entity;

use App\Import\Domain\Enum\SchedulePriority;
use App\Import\Domain\Enum\ScheduleRunStatus;
use App\Shared\Application\TenantScoped;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use LogicException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * VIEW-IMP-04 (#502) — recurring import schedule.
 *
 * Holds the cron expression, priority, optional source + profile
 * binding, and notification configuration. The cron worker daemon
 * (Symfony Scheduler tick) ships in the follow-up; V04 only writes
 * the schedule + supports manual `run-now` dispatch.
 */
class ImportSchedule extends AggregateRoot implements TenantScoped
{
    private Uuid $id;

    private ?Tenant $tenant = null;

    private Uuid $userId;

    private ?ImportSource $source = null;

    private ?ImportProfile $profile = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $name;

    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    #[Assert\Regex(pattern: '/^[a-z0-9-]+$/', message: 'Code must contain only lowercase letters, digits, and dashes.')]
    private string $code;

    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    private string $cron;

    private ?string $cronHuman = null;

    private string $priority = SchedulePriority::Normal->value;

    private bool $enabled = true;

    private ?DateTimeImmutable $nextRun = null;

    private ?DateTimeImmutable $lastRunAt = null;

    private ?string $lastRunStatus = null;

    private ?int $lastRunDurationMs = null;

    /** @var list<string> */
    private array $notifyChannels = [];

    /** @var array<string, mixed> */
    private array $notifyConfig = [];

    private DateTimeImmutable $createdAt;

    private DateTimeImmutable $updatedAt;

    public function __construct(
        Uuid $userId,
        string $name,
        string $code,
        string $cron,
        ?Uuid $id = null,
        SchedulePriority $priority = SchedulePriority::Normal,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->userId = $userId;
        $this->name = $name;
        $this->code = $code;
        $this->cron = $cron;
        $this->priority = $priority->value;
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

    public function getUserId(): Uuid
    {
        return $this->userId;
    }

    public function getSource(): ?ImportSource
    {
        return $this->source;
    }

    public function setSource(?ImportSource $source): void
    {
        $this->source = $source;
        $this->touch();
    }

    public function getProfile(): ?ImportProfile
    {
        return $this->profile;
    }

    public function setProfile(?ImportProfile $profile): void
    {
        $this->profile = $profile;
        $this->touch();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        $this->name = $name;
        $this->touch();
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
        $this->touch();
    }

    public function getCron(): string
    {
        return $this->cron;
    }

    public function setCron(string $cron, ?string $human = null): void
    {
        $this->cron = $cron;
        $this->cronHuman = $human;
        $this->touch();
    }

    public function getCronHuman(): ?string
    {
        return $this->cronHuman;
    }

    public function getPriority(): SchedulePriority
    {
        return SchedulePriority::from($this->priority);
    }

    public function setPriority(SchedulePriority $priority): void
    {
        $this->priority = $priority->value;
        $this->touch();
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function enable(): void
    {
        $this->enabled = true;
        $this->touch();
    }

    public function disable(): void
    {
        $this->enabled = false;
        $this->touch();
    }

    public function getNextRun(): ?DateTimeImmutable
    {
        return $this->nextRun;
    }

    public function setNextRun(?DateTimeImmutable $when): void
    {
        $this->nextRun = $when;
        $this->touch();
    }

    public function getLastRunAt(): ?DateTimeImmutable
    {
        return $this->lastRunAt;
    }

    public function getLastRunStatus(): ?ScheduleRunStatus
    {
        return null === $this->lastRunStatus ? null : ScheduleRunStatus::tryFrom($this->lastRunStatus);
    }

    public function getLastRunDurationMs(): ?int
    {
        return $this->lastRunDurationMs;
    }

    public function recordRun(ScheduleRunStatus $status, DateTimeImmutable $at, ?int $durationMs): void
    {
        $this->lastRunStatus = $status->value;
        $this->lastRunAt = $at;
        $this->lastRunDurationMs = $durationMs;
        $this->touch();
    }

    /**
     * @return list<string>
     */
    public function getNotifyChannels(): array
    {
        return $this->notifyChannels;
    }

    /**
     * @param list<string> $channels
     */
    public function setNotifyChannels(array $channels): void
    {
        $this->notifyChannels = $channels;
        $this->touch();
    }

    /**
     * @return array<string, mixed>
     */
    public function getNotifyConfig(): array
    {
        return $this->notifyConfig;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function setNotifyConfig(array $config): void
    {
        $this->notifyConfig = $config;
        $this->touch();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
