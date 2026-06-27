<?php

declare(strict_types=1);

namespace App\Integration\Generic\Domain\Entity;

use App\Integration\Generic\Domain\Enum\AuthType;
use App\Integration\Generic\Domain\Enum\ConnectionStatus;
use App\Shared\Application\TenantScoped;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use LogicException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A consumer-side connection to one external REST/JSON API (ADR-0022, epic APIC).
 *
 * Holds the transport definition — base URL, auth type, default headers, an
 * optional rate hint — plus the reversibly-encrypted credentials needed to
 * call the remote. The actual encryption-at-write + response masking lands in
 * APIC-P1-02; this entity stores the already-produced ciphertext + master-key
 * version in two columns, mirroring `TenantAgentConfig` / ADR-0017.
 *
 * `TenantScoped` + Postgres RLS isolate every connection to its tenant. Scheme
 * (http/https) and SSRF-safe descriptor validation are enforced separately
 * (APIC-P1-03/P1-04), not at this domain layer.
 */
class Connection extends AggregateRoot implements TenantScoped
{
    private Uuid $id;

    private ?Tenant $tenant = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    #[Assert\Regex(pattern: '/^[a-z0-9-]+$/', message: 'Code must contain only lowercase letters, digits, and dashes.')]
    private string $code;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $name;

    #[Assert\NotBlank]
    #[Assert\Length(max: 2048)]
    private string $baseUrl;

    private string $authType = AuthType::None->value;

    /**
     * Base64 of `nonce ‖ ciphertext ‖ tag`; null when `authType=none`. The
     * write path (APIC-P1-02) produces this via the encryption service; it is
     * never returned in API responses.
     */
    private ?string $credentialsCiphertext = null;

    /** Master-key version used to produce the ciphertext (ADR-0017); null when no credentials. */
    private ?int $credentialsKeyVersion = null;

    /** @var array<string, string> */
    private array $defaultHeaders = [];

    #[Assert\Positive]
    private ?int $rateLimitHint = null;

    private string $status = ConnectionStatus::Draft->value;

    private ?DateTimeImmutable $lastHealthCheckAt = null;

    private DateTimeImmutable $createdAt;

    private DateTimeImmutable $updatedAt;

    public function __construct(
        string $code,
        string $name,
        string $baseUrl,
        AuthType $authType = AuthType::None,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->code = $code;
        $this->name = $name;
        $this->baseUrl = $baseUrl;
        $this->authType = $authType->value;
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

    public function getCode(): string
    {
        return $this->code;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
        $this->touch();
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    public function setBaseUrl(string $baseUrl): void
    {
        $this->baseUrl = $baseUrl;
        $this->touch();
    }

    public function getAuthType(): AuthType
    {
        return AuthType::from($this->authType);
    }

    public function setAuthType(AuthType $authType): void
    {
        $this->authType = $authType->value;
        $this->touch();
    }

    public function getCredentialsCiphertext(): ?string
    {
        return $this->credentialsCiphertext;
    }

    public function getCredentialsKeyVersion(): ?int
    {
        return $this->credentialsKeyVersion;
    }

    /**
     * Stores an already-encrypted credential blob (ciphertext + key version)
     * or clears it (both null). The encryption itself is owned by the write
     * path (APIC-P1-02), never by this entity.
     */
    public function setCredentials(?string $ciphertext, ?int $keyVersion): void
    {
        $this->credentialsCiphertext = $ciphertext;
        $this->credentialsKeyVersion = $keyVersion;
        $this->touch();
    }

    /**
     * @return array<string, string>
     */
    public function getDefaultHeaders(): array
    {
        return $this->defaultHeaders;
    }

    /**
     * @param array<string, string> $headers
     */
    public function setDefaultHeaders(array $headers): void
    {
        $this->defaultHeaders = $headers;
        $this->touch();
    }

    public function getRateLimitHint(): ?int
    {
        return $this->rateLimitHint;
    }

    public function setRateLimitHint(?int $hint): void
    {
        $this->rateLimitHint = $hint;
        $this->touch();
    }

    public function getStatus(): ConnectionStatus
    {
        return ConnectionStatus::from($this->status);
    }

    public function setStatus(ConnectionStatus $status): void
    {
        $this->status = $status->value;
        $this->touch();
    }

    public function getLastHealthCheckAt(): ?DateTimeImmutable
    {
        return $this->lastHealthCheckAt;
    }

    /**
     * Records the outcome of a connection test / health probe (APIC-P1-05):
     * stamps the timestamp and updates the lifecycle status.
     */
    public function recordHealthCheck(DateTimeImmutable $at, ConnectionStatus $status): void
    {
        $this->lastHealthCheckAt = $at;
        $this->status = $status->value;
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
