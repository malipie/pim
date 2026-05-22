<?php

declare(strict_types=1);

namespace App\Channel\Domain\Entity;

use App\Shared\Application\TenantScoped;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use DomainException;
use LogicException;
use Symfony\Component\Uid\Uuid;

/**
 * Per-tenant activation of a global `Locale` from the ISO catalog.
 *
 * One row per (tenant, locale). Carries the tenant's locale configuration:
 *
 *  - `isDefault`  — exactly one per tenant (enforced by partial unique
 *    index `tenant_locales_one_default_per_tenant`). Ultimate fallback
 *    target; new attributes default to this locale; cannot be deactivated.
 *  - `isMandatory` — locale counts towards completeness scoring. The
 *    default locale is *always* mandatory regardless of this flag's value
 *    (asserted at the application layer; see #873 / LOC-05).
 *  - `fallback`   — optional single-locale fallback chain link. Cycle
 *    detection is enforced by `LocaleFallbackCycleDetector` (#872 /
 *    LOC-04) and a DB CHECK that blocks self-fallback.
 *  - `sortOrder`  — operator-defined display order in `/settings/locales`.
 *  - `isActive`   — soft delete. Deactivation preserves `object_values`
 *    rows; `purge` is a separate explicit operation (#871 / LOC-03).
 */
class TenantLocale implements TenantScoped
{
    private Uuid $id;

    private ?Tenant $tenant = null;

    private Locale $locale;

    private bool $isDefault = false;

    private bool $isMandatory = false;

    private ?Locale $fallback = null;

    private int $sortOrder = 0;

    private bool $isActive = true;

    private DateTimeImmutable $createdAt;

    public function __construct(
        Locale $locale,
        bool $isDefault = false,
        bool $isMandatory = false,
        ?Locale $fallback = null,
        int $sortOrder = 0,
        ?Tenant $tenant = null,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->tenant = $tenant;
        $this->locale = $locale;
        $this->isDefault = $isDefault;
        $this->isMandatory = $isMandatory || $isDefault;
        $this->fallback = $fallback;
        $this->sortOrder = $sortOrder;
        $this->createdAt = new DateTimeImmutable();
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

    public function getLocale(): Locale
    {
        return $this->locale;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function isMandatory(): bool
    {
        return $this->isMandatory;
    }

    public function getFallback(): ?Locale
    {
        return $this->fallback;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function markAsDefault(): void
    {
        $this->isDefault = true;
        $this->isMandatory = true;
        $this->isActive = true;
    }

    public function unmarkAsDefault(): void
    {
        $this->isDefault = false;
    }

    public function setMandatory(bool $mandatory): void
    {
        if ($this->isDefault && !$mandatory) {
            throw new DomainException('Default locale must remain mandatory.');
        }
        $this->isMandatory = $mandatory;
    }

    public function setFallback(?Locale $fallback): void
    {
        if (null !== $fallback && $fallback->getId()->equals($this->locale->getId())) {
            throw new DomainException('Locale cannot fall back to itself.');
        }
        $this->fallback = $fallback;
    }

    public function setSortOrder(int $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }

    public function deactivate(): void
    {
        if ($this->isDefault) {
            throw new DomainException('Default locale cannot be deactivated.');
        }
        $this->isActive = false;
    }

    public function reactivate(): void
    {
        $this->isActive = true;
    }
}
