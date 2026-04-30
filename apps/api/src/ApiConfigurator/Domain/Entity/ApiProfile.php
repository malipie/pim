<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Domain\Entity;

use App\ApiConfigurator\Domain\Enum\OutputFormat;
use App\Shared\Application\TenantScoped;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use LogicException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A named projection over the public API for a class of integrators.
 *
 * Per ADR-009 the profile filter is parameterised on `objectTypeIds`
 * (UUID list of `ObjectType` rows visible through this profile) plus
 * `includedAttributes` (attribute codes to publish) plus `filters`
 * (JSONB dict of canonical filter parameters, e.g. `status=enabled`).
 * One canonical request — `GET /api/products` keyed by an `ApiKey`
 * scoped to a profile — narrows to a per-profile view by the
 * serializer context built from this row (#94).
 *
 * Webhook fields (`webhookUrl` + `webhookEvents`) are configuration
 * only at this point. Delivery + retry policy lands in #93.
 */
class ApiProfile implements TenantScoped
{
    private Uuid $id;

    private ?Tenant $tenant = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    #[Assert\Regex('/^[a-z0-9_-]+$/', message: 'Profile code must be lowercase letters, digits, dashes or underscores.')]
    private string $code;

    #[Assert\NotBlank]
    #[Assert\Length(max: 128)]
    private string $name;

    private ?string $description = null;

    private OutputFormat $outputFormat;

    /**
     * UUID strings of `App\Catalog\Domain\Entity\ObjectType` rows visible
     * through this profile. Empty array means "no ObjectType selected"
     * — by contract the profile resolves to an empty result set rather
     * than to "everything", so a half-configured profile leaks nothing.
     *
     * @var list<string>
     */
    private array $objectTypeIds = [];

    /**
     * Attribute codes (not UUIDs — codes are stable across tenants and
     * survive seed re-runs) to publish through this profile. Filtered
     * server-side at serializer time (#94) — fields outside the list
     * are stripped before render.
     *
     * @var list<string>
     */
    private array $includedAttributes = [];

    /**
     * Canonical query filters applied on every request through this
     * profile. Shape mirrors the `?status=…&completeness[gte]=…` query
     * parameters consumed by the existing `Catalog` filters (#43).
     *
     * @var array<string, mixed>
     */
    private array $filters = [];

    #[Assert\Length(max: 2048)]
    #[Assert\Url(requireTld: false)]
    private ?string $webhookUrl = null;

    /**
     * Domain event names this profile subscribes to (e.g.
     * `object.created.product`, `object.published.product`). Wired in
     * #93 against the publisher from #47.
     *
     * @var list<string>
     */
    private array $webhookEvents = [];

    #[Assert\Positive]
    #[Assert\LessThanOrEqual(100000)]
    private int $rateLimitPerHour;

    private DateTimeImmutable $createdAt;

    private DateTimeImmutable $updatedAt;

    /**
     * @param list<string>         $objectTypeIds
     * @param list<string>         $includedAttributes
     * @param array<string, mixed> $filters
     * @param list<string>         $webhookEvents
     */
    public function __construct(
        string $code,
        string $name,
        OutputFormat $outputFormat,
        array $objectTypeIds = [],
        array $includedAttributes = [],
        array $filters = [],
        ?string $description = null,
        ?string $webhookUrl = null,
        array $webhookEvents = [],
        int $rateLimitPerHour = 1000,
        ?Uuid $id = null,
        ?DateTimeImmutable $createdAt = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->code = $code;
        $this->name = $name;
        $this->outputFormat = $outputFormat;
        $this->objectTypeIds = $objectTypeIds;
        $this->includedAttributes = $includedAttributes;
        $this->filters = $filters;
        $this->description = $description;
        $this->webhookUrl = $webhookUrl;
        $this->webhookEvents = $webhookEvents;
        $this->rateLimitPerHour = $rateLimitPerHour;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
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

    public function getCode(): string
    {
        return $this->code;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
        $this->touch();
    }

    public function getOutputFormat(): OutputFormat
    {
        return $this->outputFormat;
    }

    public function setOutputFormat(OutputFormat $outputFormat): void
    {
        $this->outputFormat = $outputFormat;
        $this->touch();
    }

    /**
     * @return list<string>
     */
    public function getObjectTypeIds(): array
    {
        return $this->objectTypeIds;
    }

    /**
     * @param list<string> $objectTypeIds
     */
    public function setObjectTypeIds(array $objectTypeIds): void
    {
        $this->objectTypeIds = $objectTypeIds;
        $this->touch();
    }

    /**
     * @return list<string>
     */
    public function getIncludedAttributes(): array
    {
        return $this->includedAttributes;
    }

    /**
     * @param list<string> $includedAttributes
     */
    public function setIncludedAttributes(array $includedAttributes): void
    {
        $this->includedAttributes = $includedAttributes;
        $this->touch();
    }

    /**
     * @return array<string, mixed>
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function setFilters(array $filters): void
    {
        $this->filters = $filters;
        $this->touch();
    }

    public function getWebhookUrl(): ?string
    {
        return $this->webhookUrl;
    }

    public function setWebhookUrl(?string $webhookUrl): void
    {
        $this->webhookUrl = $webhookUrl;
        $this->touch();
    }

    /**
     * @return list<string>
     */
    public function getWebhookEvents(): array
    {
        return $this->webhookEvents;
    }

    /**
     * @param list<string> $webhookEvents
     */
    public function setWebhookEvents(array $webhookEvents): void
    {
        $this->webhookEvents = $webhookEvents;
        $this->touch();
    }

    public function getRateLimitPerHour(): int
    {
        return $this->rateLimitPerHour;
    }

    public function setRateLimitPerHour(int $rateLimitPerHour): void
    {
        $this->rateLimitPerHour = $rateLimitPerHour;
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
