<?php

declare(strict_types=1);

namespace App\Identity\Domain\Entity;

use DateTimeImmutable;
use LogicException;
use Symfony\Component\Uid\Uuid;

/**
 * Magic-link invitation for onboarding a new user into a tenant.
 *
 * Flow (Phase 2 ticket #657 — magic link invitation flow):
 *  1. Tenant admin creates `Invitation` with target email + role assignment.
 *  2. Email sent with link `/accept-invitation?token={plaintext}`.
 *  3. Recipient opens link → token hash matched against `tokenHash`.
 *  4. On accept: `User` created, `UserRole` assignment created, `acceptedAt`
 *     stamped, link rendered single-use.
 *
 * Security properties:
 *  - `tokenHash` — SHA-256 hex of the plaintext (plaintext only ever in the
 *    email body). 7-day TTL by default (`expiresAt`).
 *  - Single-use semantics enforced by `acceptedAt` non-NULL → reject reuse.
 *  - Revocable by tenant admin before acceptance via `revoke()`.
 *
 * `roleId` denormalised for hot-path acceptance query (no join). Schema-level
 * FK in P1-004 migration enforces referential integrity.
 */
class Invitation
{
    public const string STATUS_PENDING = 'pending';
    public const string STATUS_ACCEPTED = 'accepted';
    public const string STATUS_REVOKED = 'revoked';
    public const string STATUS_EXPIRED = 'expired';

    private Uuid $id;

    private Uuid $tenantId;

    private string $email;

    private string $tokenHash;

    private Uuid $invitedByUserId;

    private Uuid $roleId;

    private DateTimeImmutable $expiresAt;

    private ?DateTimeImmutable $acceptedAt = null;

    private ?DateTimeImmutable $revokedAt = null;

    private DateTimeImmutable $createdAt;

    public function __construct(
        Uuid $tenantId,
        string $email,
        string $tokenHash,
        Uuid $invitedByUserId,
        Uuid $roleId,
        DateTimeImmutable $expiresAt,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->tenantId = $tenantId;
        $this->email = $email;
        $this->tokenHash = $tokenHash;
        $this->invitedByUserId = $invitedByUserId;
        $this->roleId = $roleId;
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

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getInvitedByUserId(): Uuid
    {
        return $this->invitedByUserId;
    }

    public function getRoleId(): Uuid
    {
        return $this->roleId;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getAcceptedAt(): ?DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function getRevokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isAccepted(): bool
    {
        return null !== $this->acceptedAt;
    }

    public function isRevoked(): bool
    {
        return null !== $this->revokedAt;
    }

    public function isExpired(?DateTimeImmutable $now = null): bool
    {
        return $this->expiresAt <= ($now ?? new DateTimeImmutable());
    }

    public function isPending(?DateTimeImmutable $now = null): bool
    {
        return !$this->isAccepted() && !$this->isRevoked() && !$this->isExpired($now);
    }

    public function getStatus(?DateTimeImmutable $now = null): string
    {
        if ($this->isAccepted()) {
            return self::STATUS_ACCEPTED;
        }
        if ($this->isRevoked()) {
            return self::STATUS_REVOKED;
        }
        if ($this->isExpired($now)) {
            return self::STATUS_EXPIRED;
        }

        return self::STATUS_PENDING;
    }

    public function accept(?DateTimeImmutable $when = null): void
    {
        if ($this->isAccepted()) {
            throw new LogicException('Invitation already accepted.');
        }
        if ($this->isRevoked()) {
            throw new LogicException('Invitation revoked.');
        }
        if ($this->isExpired($when)) {
            throw new LogicException('Invitation expired.');
        }
        $this->acceptedAt = $when ?? new DateTimeImmutable();
    }

    public function revoke(?DateTimeImmutable $when = null): void
    {
        if ($this->isAccepted()) {
            throw new LogicException('Cannot revoke an already-accepted invitation.');
        }
        $this->revokedAt = $when ?? new DateTimeImmutable();
    }
}
