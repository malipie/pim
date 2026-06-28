<?php

declare(strict_types=1);

namespace App\Integration\Generic\Domain\Entity;

use App\Integration\Generic\Domain\Enum\RemoteFieldDataType;
use App\Shared\Application\TenantScoped;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use LogicException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * One field of an external API response — discovered from a sample or entered
 * by hand (ADR-0022, epic APIC, ticket APIC-P2-02).
 *
 * A {@see RemoteEndpoint} owns many fields; each carries the JSONPath that
 * locates it within a record, a human label, the detected {@see RemoteFieldDataType}
 * and a textual sample value. These are the left-hand side of the 1:1 field
 * mapping (APIC-P2-07/08).
 *
 * `TenantScoped` + Postgres RLS isolate every field to its tenant; the tenant
 * always matches the parent endpoint's. The FK to RemoteEndpoint cascades on
 * delete.
 */
class RemoteField extends AggregateRoot implements TenantScoped
{
    private Uuid $id;

    private ?Tenant $tenant = null;

    private RemoteEndpoint $endpoint;

    #[Assert\NotBlank]
    #[Assert\Length(max: 512)]
    private string $path;

    #[Assert\Length(max: 255)]
    private ?string $label = null;

    private string $dataType;

    /** Textual sample value captured during discovery; null when none was seen. */
    #[Assert\Length(max: 2048)]
    private ?string $sampleValue = null;

    private DateTimeImmutable $createdAt;

    private DateTimeImmutable $updatedAt;

    public function __construct(
        RemoteEndpoint $endpoint,
        string $path,
        RemoteFieldDataType $dataType = RemoteFieldDataType::String,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->endpoint = $endpoint;
        $this->path = $path;
        $this->dataType = $dataType->value;
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

    public function getEndpoint(): RemoteEndpoint
    {
        return $this->endpoint;
    }

    public function getEndpointId(): Uuid
    {
        return $this->endpoint->getId();
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): void
    {
        $this->path = $path;
        $this->touch();
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
        $this->touch();
    }

    public function getDataType(): RemoteFieldDataType
    {
        return RemoteFieldDataType::from($this->dataType);
    }

    public function setDataType(RemoteFieldDataType $dataType): void
    {
        $this->dataType = $dataType->value;
        $this->touch();
    }

    public function getSampleValue(): ?string
    {
        return $this->sampleValue;
    }

    public function setSampleValue(?string $sampleValue): void
    {
        $this->sampleValue = $sampleValue;
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
