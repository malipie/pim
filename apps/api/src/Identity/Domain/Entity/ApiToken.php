<?php

declare(strict_types=1);

namespace App\Identity\Domain\Entity;

use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Tenant-scoped API token used as alternative auth method (vs JWT).
 *
 * Storage shape (PRD-PIM-rbac §4.3):
 *  - `tokenHash` — BCrypt hash; the plaintext leaves the server exactly once,
 *    in the create response. Subsequent reads expose only `tokenLast4`.
 *  - `tokenLast4` — last 4 characters of the plaintext (display purpose only).
 *  - Token format `cortex_{tenant_short}_{random32}` (Phase 2 ticket #652) —
 *    the `cortex_` prefix lets gitleaks/TruffleHog detect leaks in CI.
 *
 * `tenantId` / `userId` denormalised for hot-path lookups (every authenticated
 * request hashes the incoming token and queries by `tokenHash`). Schema-level
 * FKs in P1-004 migrations enforce referential integrity.
 *
 * `lastUsedAt` / `lastUsedIp` updated async by `UpdateTokenLastUsedMessage`
 * (Phase 2 ticket #652) — never blocking the request.
 *
 * `expiresAt` NULL means "never expires" (token lives until explicit revoke).
 * `revokedAt` non-NULL → token rejected at auth time with 401 *„Token revoked"*.
 */
class ApiToken
{
    private Uuid $id;

    private Uuid $tenantId;

    private Uuid $userId;

    private string $name;

    private string $tokenHash;

    private string $tokenLast4;

    /**
     * Scope identifiers (`read-only`, `read-write-catalog`, etc.). Empty array
     * means "no scopes" → token rejects every request.
     *
     * @var list<string>
     */
    private array $scopes;

    private ?DateTimeImmutable $expiresAt;

    private ?DateTimeImmutable $revokedAt = null;

    private ?DateTimeImmutable $lastUsedAt = null;

    private ?string $lastUsedIp = null;

    private DateTimeImmutable $createdAt;

    /**
     * @param list<string> $scopes
     */
    public function __construct(
        Uuid $tenantId,
        Uuid $userId,
        string $name,
        string $tokenHash,
        string $tokenLast4,
        array $scopes,
        ?DateTimeImmutable $expiresAt = null,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->tenantId = $tenantId;
        $this->userId = $userId;
        $this->name = $name;
        $this->tokenHash = $tokenHash;
        $this->tokenLast4 = $tokenLast4;
        $this->scopes = $scopes;
        $this->expiresAt = $expiresAt;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTenantId(): Uuid
    {
        return $this->tenantId;
    }

    public function getUserId(): Uuid
    {
        return $this->userId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getTokenLast4(): string
    {
        return $this->tokenLast4;
    }

    /**
     * @return list<string>
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function hasScope(string $scope): bool
    {
        return \in_array($scope, $this->scopes, true);
    }

    public function getExpiresAt(): ?DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getRevokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function isRevoked(): bool
    {
        return null !== $this->revokedAt;
    }

    public function isExpired(?DateTimeImmutable $now = null): bool
    {
        if (null === $this->expiresAt) {
            return false;
        }

        return $this->expiresAt <= ($now ?? new DateTimeImmutable());
    }

    public function isActive(?DateTimeImmutable $now = null): bool
    {
        return !$this->isRevoked() && !$this->isExpired($now);
    }

    public function revoke(?DateTimeImmutable $when = null): void
    {
        $this->revokedAt = $when ?? new DateTimeImmutable();
    }

    public function getLastUsedAt(): ?DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function getLastUsedIp(): ?string
    {
        return $this->lastUsedIp;
    }

    public function recordUsage(?string $ip = null, ?DateTimeImmutable $when = null): void
    {
        $this->lastUsedAt = $when ?? new DateTimeImmutable();
        $this->lastUsedIp = $ip;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
