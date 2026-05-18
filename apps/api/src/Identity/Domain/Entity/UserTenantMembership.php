<?php

declare(strict_types=1);

namespace App\Identity\Domain\Entity;

use DateTimeImmutable;
use LogicException;
use Symfony\Component\Uid\Uuid;

/**
 * User membership in a tenant — supports a single user holding access to
 * multiple tenants (consultant scenario, multi-org admin).
 *
 * Phase 2 (#656 `/api/me`) reads the membership list for the authenticated
 * user and returns the active tenant alongside the eligible tenant list.
 * Phase 4 (#683 tenant-switch dropdown) lets the user pivot between tenants
 * without re-authenticating.
 *
 * Status semantics:
 *  - `pending` — membership created (e.g. via invitation) but user has not
 *    yet authenticated as this tenant.
 *  - `active` — user has joined; permission checks resolve through the user's
 *    `UserRole` assignments scoped to this tenant.
 *  - `suspended` — temporarily disabled by tenant admin; user keeps the row
 *    for re-enable but auth resolution rejects.
 *  - `revoked` — terminal; row kept for audit, user removed from tenant.
 */
class UserTenantMembership
{
    public const string STATUS_PENDING = 'pending';
    public const string STATUS_ACTIVE = 'active';
    public const string STATUS_SUSPENDED = 'suspended';
    public const string STATUS_REVOKED = 'revoked';

    private Uuid $id;

    private Uuid $userId;

    private Uuid $tenantId;

    private string $status;

    private DateTimeImmutable $invitedAt;

    private ?DateTimeImmutable $joinedAt = null;

    private ?DateTimeImmutable $revokedAt = null;

    public function __construct(
        Uuid $userId,
        Uuid $tenantId,
        ?Uuid $id = null,
        ?DateTimeImmutable $invitedAt = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->userId = $userId;
        $this->tenantId = $tenantId;
        $this->status = self::STATUS_PENDING;
        $this->invitedAt = $invitedAt ?? new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUserId(): Uuid
    {
        return $this->userId;
    }

    public function getTenantId(): Uuid
    {
        return $this->tenantId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return self::STATUS_ACTIVE === $this->status;
    }

    public function isPending(): bool
    {
        return self::STATUS_PENDING === $this->status;
    }

    public function isSuspended(): bool
    {
        return self::STATUS_SUSPENDED === $this->status;
    }

    public function isRevoked(): bool
    {
        return self::STATUS_REVOKED === $this->status;
    }

    public function getInvitedAt(): DateTimeImmutable
    {
        return $this->invitedAt;
    }

    public function getJoinedAt(): ?DateTimeImmutable
    {
        return $this->joinedAt;
    }

    public function getRevokedAt(): ?DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function activate(?DateTimeImmutable $when = null): void
    {
        if (self::STATUS_REVOKED === $this->status) {
            throw new LogicException('Cannot reactivate a revoked membership; create a new row instead.');
        }
        if (null === $this->joinedAt) {
            $this->joinedAt = $when ?? new DateTimeImmutable();
        }
        $this->status = self::STATUS_ACTIVE;
    }

    public function suspend(): void
    {
        if (self::STATUS_REVOKED === $this->status) {
            throw new LogicException('Cannot suspend a revoked membership.');
        }
        $this->status = self::STATUS_SUSPENDED;
    }

    public function revoke(?DateTimeImmutable $when = null): void
    {
        $this->status = self::STATUS_REVOKED;
        $this->revokedAt = $when ?? new DateTimeImmutable();
    }
}
