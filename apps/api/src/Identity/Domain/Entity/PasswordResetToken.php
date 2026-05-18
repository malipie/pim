<?php

declare(strict_types=1);

namespace App\Identity\Domain\Entity;

use DateTimeImmutable;
use LogicException;
use Symfony\Component\Uid\Uuid;

/**
 * Password-reset magic-link token (RBAC-P2-009 #658).
 *
 * Single-use, 1-hour TTL by default. Hashed SHA-256 in DB (plaintext
 * leaves the server exactly once in the reset email). Marked consumed
 * via `markUsed()` — re-use throws LogicException.
 *
 * `tenantId` denormalised for cross-tenant audit; schema-level FK in
 * the migration enforces referential integrity.
 */
class PasswordResetToken
{
    private const int TTL_HOURS = 1;

    private Uuid $id;

    private Uuid $tenantId;

    private Uuid $userId;

    private string $tokenHash;

    private DateTimeImmutable $expiresAt;

    private ?DateTimeImmutable $usedAt = null;

    private DateTimeImmutable $createdAt;

    public function __construct(
        Uuid $tenantId,
        Uuid $userId,
        string $tokenHash,
        ?DateTimeImmutable $expiresAt = null,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->tenantId = $tenantId;
        $this->userId = $userId;
        $this->tokenHash = $tokenHash;
        $this->expiresAt = $expiresAt ?? new DateTimeImmutable(\sprintf('+%d hours', self::TTL_HOURS));
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

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getUsedAt(): ?DateTimeImmutable
    {
        return $this->usedAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isUsed(): bool
    {
        return null !== $this->usedAt;
    }

    public function isExpired(?DateTimeImmutable $now = null): bool
    {
        return $this->expiresAt <= ($now ?? new DateTimeImmutable());
    }

    public function isPending(?DateTimeImmutable $now = null): bool
    {
        return !$this->isUsed() && !$this->isExpired($now);
    }

    public function markUsed(?DateTimeImmutable $when = null): void
    {
        if ($this->isUsed()) {
            throw new LogicException('Password-reset token already consumed.');
        }
        if ($this->isExpired($when)) {
            throw new LogicException('Password-reset token expired.');
        }
        $this->usedAt = $when ?? new DateTimeImmutable();
    }
}
