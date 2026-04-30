<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Domain\Entity;

use App\Shared\Application\TenantScoped;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use LogicException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Long-lived secret an external integrator presents on `/api/*`.
 *
 * The raw key never touches this row — only its Argon2id digest
 * (`keyHash`) and a 12-character display prefix (`keyPrefix`, e.g.
 * `pim_live_a4f2`) the admin uses to disambiguate keys in the UI long
 * after the secret was last visible. See ADR-0016 for the algorithm
 * and format choice.
 *
 * `scopes` is a list of {@see ApiProfile::$code} values — one key may
 * grant access to multiple profiles, but the integrator picks one per
 * request through a header (#94). `lastUsedAt` is bumped on every
 * authenticated request so the rotation playbook (#105) can spot dead
 * keys without scanning request logs.
 */
class ApiKey implements TenantScoped
{
    private Uuid $id;

    private ?Tenant $tenant = null;

    /**
     * Argon2id digest of the raw key. Verified with
     * `password_verify($raw, $hash)` on the hot path.
     */
    #[Assert\NotBlank]
    private string $keyHash;

    /**
     * First 12 characters of the raw key — `pim_<env>_<4 chars>`. Safe
     * to log and to display. Indexed for O(1) lookup before the
     * Argon2id verify (#94).
     */
    #[Assert\NotBlank]
    #[Assert\Length(min: 9, max: 32)]
    private string $keyPrefix;

    #[Assert\NotBlank]
    #[Assert\Length(max: 128)]
    private string $name;

    /**
     * Profile codes accessible by this key.
     *
     * @var list<string>
     */
    private array $scopes = [];

    private ?DateTimeImmutable $expiresAt = null;

    private ?DateTimeImmutable $revokedAt = null;

    private ?DateTimeImmutable $lastUsedAt = null;

    #[Assert\Positive]
    #[Assert\LessThanOrEqual(100000)]
    private int $rateLimitPerHour;

    private DateTimeImmutable $createdAt;

    /**
     * @param list<string> $scopes
     */
    public function __construct(
        string $keyHash,
        string $keyPrefix,
        string $name,
        array $scopes = [],
        ?DateTimeImmutable $expiresAt = null,
        int $rateLimitPerHour = 1000,
        ?Uuid $id = null,
        ?DateTimeImmutable $createdAt = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->keyHash = $keyHash;
        $this->keyPrefix = $keyPrefix;
        $this->name = $name;
        $this->scopes = $scopes;
        $this->expiresAt = $expiresAt;
        $this->rateLimitPerHour = $rateLimitPerHour;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
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

    public function getKeyHash(): string
    {
        return $this->keyHash;
    }

    /**
     * Replace the digest after `password_needs_rehash` flags the
     * algorithm parameters as outdated. Triggered from #94 on a
     * successful verify.
     */
    public function rehash(string $keyHash): void
    {
        $this->keyHash = $keyHash;
    }

    public function getKeyPrefix(): string
    {
        return $this->keyPrefix;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return list<string>
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    /**
     * @param list<string> $scopes
     */
    public function setScopes(array $scopes): void
    {
        $this->scopes = $scopes;
    }

    public function hasScope(string $profileCode): bool
    {
        return \in_array($profileCode, $this->scopes, true);
    }

    public function getExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getRevokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function revoke(DateTimeImmutable $when): void
    {
        if (null === $this->revokedAt) {
            $this->revokedAt = $when;
        }
    }

    public function isRevoked(): bool
    {
        return null !== $this->revokedAt;
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return null !== $this->expiresAt && $now >= $this->expiresAt;
    }

    public function isUsable(DateTimeImmutable $now): bool
    {
        return !$this->isRevoked() && !$this->isExpired($now);
    }

    public function getLastUsedAt(): ?DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function markUsed(DateTimeImmutable $when): void
    {
        $this->lastUsedAt = $when;
    }

    public function getRateLimitPerHour(): int
    {
        return $this->rateLimitPerHour;
    }

    public function setRateLimitPerHour(int $rateLimitPerHour): void
    {
        $this->rateLimitPerHour = $rateLimitPerHour;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
