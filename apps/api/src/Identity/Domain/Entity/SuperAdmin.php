<?php

declare(strict_types=1);

namespace App\Identity\Domain\Entity;

use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Platform-level operator (Marcin, DBA) — cross-tenant by design, no tenant_id.
 *
 * SuperAdmin sits outside the tenant boundary: instances of this entity never
 * route through TenantFilter and never appear in tenant-scoped queries. The
 * audit trail in `audit_logs` flags every SuperAdmin action with
 * `cross_tenant_access=true` so the privacy boundary is forensically traceable
 * (Phase 3 ticket #677 — Super Admin bypass + Break-glass CLI; Phase 5 ticket
 * #712 — Break-glass recovery UI).
 *
 * Authentication piggy-backs on the same password + MFA stack as tenant
 * `User`s — JWT issued at login carries `super_admin=true` claim so the
 * authenticator can resolve the principal type without joining tables.
 */
class SuperAdmin
{
    public const string STATUS_ACTIVE = 'active';
    public const string STATUS_DISABLED = 'disabled';

    private Uuid $id;

    private string $email;

    private string $passwordHash;

    private string $name;

    private string $status;

    private ?DateTimeImmutable $lastLoginAt = null;

    private DateTimeImmutable $createdAt;

    public function __construct(
        string $email,
        string $passwordHash,
        string $name,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->email = $email;
        $this->passwordHash = $passwordHash;
        $this->name = $name;
        $this->status = self::STATUS_ACTIVE;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return self::STATUS_ACTIVE === $this->status;
    }

    public function disable(): void
    {
        $this->status = self::STATUS_DISABLED;
    }

    public function enable(): void
    {
        $this->status = self::STATUS_ACTIVE;
    }

    public function getLastLoginAt(): ?DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function recordLogin(?DateTimeImmutable $when = null): void
    {
        $this->lastLoginAt = $when ?? new DateTimeImmutable();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
