<?php

declare(strict_types=1);

namespace App\Identity\Domain\Entity;

use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * RBAC-P3-013 (#676) — RBAC-aware audit log entry per PRD §4.3.
 *
 * Orthogonal to the dh-auditor bundle's per-entity `*_audit` tables —
 * those track domain entity mutations, this one tracks
 * permission-check decisions + cross-tenant Super Admin actions on the
 * audit_logs table (schema introduced by RBAC-P1-005 #644 in
 * `Version20260518160000`).
 *
 * The entity is intentionally **append-only** — no setters past the
 * constructor, no `update()` flow. Audit entries are immutable by design
 * once written; corrections / retractions land as a new entry referencing
 * the original via `specialFlags`.
 *
 * `tenantId` is nullable because Super Admin cross-tenant operations
 * may carry no tenant context (e.g. listing tenants on the admin
 * subdomain). Tenant FK has ON DELETE SET NULL — deleted tenant clears
 * the link but preserves the entry for compliance.
 *
 * `oldValue` / `newValue` are JSON payloads diffed at the call site;
 * scrubbing for sensitive fields is the {@see AuditLogScrubber}'s
 * responsibility before the entry is constructed.
 */
final class AuditLog
{
    /**
     * @param array<string, mixed>|null $oldValue
     * @param array<string, mixed>|null $newValue
     * @param list<string>              $specialFlags
     */
    public function __construct(
        private readonly Uuid $id,
        private readonly ?Uuid $tenantId,
        private readonly ?Uuid $userId,
        private readonly ?Uuid $superAdminId,
        private readonly string $action,
        private readonly string $resourceType,
        private readonly ?string $resourceId,
        private readonly ?array $oldValue,
        private readonly ?array $newValue,
        private readonly ?string $permissionCheckResult,
        private readonly bool $crossTenantAccess,
        private readonly array $specialFlags,
        private readonly ?string $ipAddress,
        private readonly ?string $userAgent,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTenantId(): ?Uuid
    {
        return $this->tenantId;
    }

    public function getUserId(): ?Uuid
    {
        return $this->userId;
    }

    public function getSuperAdminId(): ?Uuid
    {
        return $this->superAdminId;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getResourceType(): string
    {
        return $this->resourceType;
    }

    public function getResourceId(): ?string
    {
        return $this->resourceId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getOldValue(): ?array
    {
        return $this->oldValue;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getNewValue(): ?array
    {
        return $this->newValue;
    }

    public function getPermissionCheckResult(): ?string
    {
        return $this->permissionCheckResult;
    }

    public function isCrossTenantAccess(): bool
    {
        return $this->crossTenantAccess;
    }

    /**
     * @return list<string>
     */
    public function getSpecialFlags(): array
    {
        return $this->specialFlags;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public static function forPermissionCheck(
        ?Tenant $tenant,
        ?Uuid $userId,
        string $action,
        string $resourceType,
        ?string $resourceId,
        string $permissionCheckResult,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): self {
        return new self(
            id: Uuid::v7(),
            tenantId: $tenant?->getId(),
            userId: $userId,
            superAdminId: null,
            action: $action,
            resourceType: $resourceType,
            resourceId: $resourceId,
            oldValue: null,
            newValue: null,
            permissionCheckResult: $permissionCheckResult,
            crossTenantAccess: false,
            specialFlags: [],
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            createdAt: new DateTimeImmutable(),
        );
    }
}
